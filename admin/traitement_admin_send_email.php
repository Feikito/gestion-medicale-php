<?php
session_start();
require '../php/db.php'; 
require_once '../php/utils/csrf_utils.php';
require_once '../php/utils/email_functions.php'; 
require_once __DIR__ . '/../php/utils/email_template.php';
require_once '../php/utils/logger.php'; // AJOUTÉ

if (!isset($_SESSION['admin_id'])) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé.', 'users' => []]);
        exit;
    }
    $_SESSION['flash_message_login'] = "Accès refusé.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/admin-login.php'); 
    exit;
}

$admin_id_for_log = $_SESSION['admin_id'] ?? null;
$redirect_form_page = 'envoyer_email_specifique.php';

if (isset($_GET['action']) && $_GET['action'] === 'search_users') {
    header('Content-Type: application/json');
    $searchTerm = trim($_GET['term'] ?? '');
    $userType = trim($_GET['type'] ?? '');
    $results = [];

    if (strlen($searchTerm) >= 2 && in_array($userType, ['patient', 'medecin'])) {
        $table = ($userType === 'patient') ? 'patients' : 'medecins';
        if ($pdo->query("SHOW TABLES LIKE '$table'")->rowCount() == 0) {
            echo json_encode(['error' => "Source de données non disponible pour '$userType'.", 'users' => []]);
            exit;
        }
        $sql = "SELECT id, nom, prenom, email FROM $table WHERE (LOWER(CONCAT(prenom, ' ', nom)) LIKE LOWER(:term_concat_pn_np) OR LOWER(CONCAT(nom, ' ', prenom)) LIKE LOWER(:term_concat_np_pn) OR LOWER(email) LIKE LOWER(:term_email) OR LOWER(nom) LIKE LOWER(:term_nom) OR LOWER(prenom) LIKE LOWER(:term_prenom)) AND email IS NOT NULL AND email != '' LIMIT 10";
        try {
            $stmt = $pdo->prepare($sql);
            $searchTermWithWildcards = "%$searchTerm%";
            $stmt->bindValue(':term_concat_pn_np', $searchTermWithWildcards, PDO::PARAM_STR);
            $stmt->bindValue(':term_concat_np_pn', $searchTermWithWildcards, PDO::PARAM_STR);
            $stmt->bindValue(':term_email', $searchTermWithWildcards, PDO::PARAM_STR);
            $stmt->bindValue(':term_nom', $searchTermWithWildcards, PDO::PARAM_STR);
            $stmt->bindValue(':term_prenom', $searchTermWithWildcards, PDO::PARAM_STR);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur recherche utilisateur AJAX (Admin): " . $e->getMessage());
            echo json_encode(['error' => 'Erreur serveur lors de la recherche.', 'users' => []]);
            exit;
        }
    }
    echo json_encode(['users' => $results]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . $redirect_form_page);
    exit();
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) {
    $_SESSION['flash_message'] = "Erreur de sécurité (CSRF).";
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $redirect_form_page);
    exit;
}

$sujet = trim($_POST['sujet'] ?? '');
$message_html_brut_admin = trim($_POST['message'] ?? '');
$selected_user_ids_raw = $_POST['selected_user_ids'] ?? []; 

$_SESSION['form_data_admin_email_spec'] = $_POST; 
$errors = [];
if (empty($sujet)) $errors['sujet'] = "Le sujet de l'email est requis.";
if (empty($message_html_brut_admin)) $errors['message'] = "Le message de l'email est requis.";
if (empty($selected_user_ids_raw) || !is_array($selected_user_ids_raw)) {
    $errors['selected_user_ids'] = "Aucun destinataire n'a été sélectionné.";
}

$destinataires_final = []; 
$destinataires_ids_pour_log = [];
if (is_array($selected_user_ids_raw)) {
    foreach ($selected_user_ids_raw as $user_entry) {
        if (is_string($user_entry) && strpos($user_entry, ':') !== false) {
            list($type, $id_str) = explode(':', $user_entry, 2);
            $id = filter_var($id_str, FILTER_VALIDATE_INT);
            if ($id && in_array($type, ['patient', 'medecin'])) {
                $destinataires_final[] = ['type' => $type, 'id' => $id];
                $destinataires_ids_pour_log[] = $user_entry; // Garder type:id pour le log
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
    $_SESSION['selected_users_ids_admin_email_spec'] = $destinataires_final;
    header("Location: " . $redirect_form_page);
    exit();
}

$emails_envoyes = 0;
$emails_echoues = 0;
$nom_expediteur_email_admin = defined('ADMIN_NAME_NOTIFICATIONS') ? ADMIN_NAME_NOTIFICATIONS : (defined('NOM_APPLICATION') ? NOM_APPLICATION . ' Admin' : 'Administration SANTE TV');
$email_expediteur_admin = defined('ADMIN_EMAIL_NOTIFICATIONS') ? ADMIN_EMAIL_NOTIFICATIONS : (defined('EMAIL_SYSTEM_FROM_ADDRESS') ? EMAIL_SYSTEM_FROM_ADDRESS : 'nepasrepondre@santetv.ma');
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];

foreach ($destinataires_final as $dest_info) {
    $table_fetch = ($dest_info['type'] === 'patient') ? 'patients' : 'medecins';
    try {
        if ($pdo->query("SHOW TABLES LIKE '$table_fetch'")->rowCount() == 0) {
            $emails_echoues++;
            error_log("Table '$table_fetch' non trouvée pour envoi email admin (ID: ".$dest_info['id'].")");
            continue;
        }
        $stmt_user = $pdo->prepare("SELECT email, prenom, nom FROM $table_fetch WHERE id = ? AND email IS NOT NULL AND email != ''");
        $stmt_user->execute([$dest_info['id']]);
        $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($user && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            $nom_complet_destinataire = trim(htmlspecialchars(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? 'Utilisateur')));
            if ($dest_info['type'] === 'medecin' && strpos($nom_complet_destinataire, 'Dr. ') !== 0 && !empty(trim($nom_complet_destinataire))) {
                $nom_complet_destinataire = "Dr. " . $nom_complet_destinataire;
            }
            $message_personnalise_contenu = str_replace('%NOM_UTILISATEUR%', $nom_complet_destinataire, $message_html_brut_admin);
            $corps_html_final = get_email_html_layout($sujet, $message_personnalise_contenu, "SANTE TV");

            if (envoyer_email($user['email'], $nom_complet_destinataire, $sujet, $corps_html_final, '', $email_expediteur_admin, $nom_expediteur_email_admin)) {
                $emails_envoyes++;
            } else {
                $emails_echoues++;
                error_log("Échec envoi à " . $user['email'] . " (ID: ".$dest_info['type']."-".$dest_info['id'].") par admin ID: " . $admin_id_for_log);
            }
        } else {
            $emails_echoues++;
            error_log("Destinataire invalide ou email manquant pour ID: ".$dest_info['type']."-".$dest_info['id']." lors d'un envoi par admin ID: ".$admin_id_for_log);
        }
    } catch (PDOException $e) {
        $emails_echoues++;
        error_log("Erreur PDO envoi email spécifique à ID: ".$dest_info['type']."-".$dest_info['id']." par admin ID: " . $admin_id_for_log . " : " . $e->getMessage());
    }
    if (($emails_envoyes + $emails_echoues) % 10 == 0) {
        sleep(1); 
    }
}

log_action_application(
    $pdo,
    'ENVOI_EMAIL_SPECIFIQUE',
    "Email spécifique envoyé. Sujet: '$sujet'. Destinataires: " . implode(', ', $destinataires_ids_pour_log) . ". Envoyés: $emails_envoyes, Échoués: $emails_echoues.",
    null, null,
    ['destinataires' => $destinataires_ids_pour_log, 'sujet' => $sujet, 'envoyes' => $emails_envoyes, 'echoues' => $emails_echoues]
);

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