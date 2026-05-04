<?php
session_start(); 
require '../php/db.php'; 
require_once '../php/utils/email_functions.php'; 
require_once '../php/utils/email_template.php';
require_once '../php/utils/logger.php'; // AJOUTÉ
require_once '../php/utils/csrf_utils.php'; // AJOUTÉ pour la soumission POST

if (!isset($_SESSION['admin_id'])) { 
    $_SESSION['flash_message_login'] = "Accès refusé. Veuillez vous connecter.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/admin-login.php'); 
    exit;
}

$default_redirect_page_val_med = 'gestion_medecins.php'; 
$return_url_val_med = trim($_POST['return_url'] ?? $_GET['return_url'] ?? $default_redirect_page_val_med);

if (!empty($return_url_val_med)) {
    $parsed_return_url = parse_url($return_url_val_med);
    $allowed_paths = ['gestion_medecins.php', 'voir_medecin.php', 'dashboard_admin.php'];
    $path_is_allowed = false;
    if (isset($parsed_return_url['path'])) {
        $path_basename = basename($parsed_return_url['path']);
        foreach ($allowed_paths as $allowed_path) {
            if ($path_basename === $allowed_path) {
                $path_is_allowed = true;
                break;
            }
        }
    }
    if ($path_is_allowed) {
        $redirect_page_val_med = $path_basename; 
        if (isset($parsed_return_url['query'])) {
            $redirect_page_val_med .= '?' . $parsed_return_url['query'];
        }
    } else {
        $redirect_page_val_med = $default_redirect_page_val_med;
    }
} else {
    $redirect_page_val_med = $default_redirect_page_val_med;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Si méthode GET, on affiche un simple formulaire pour confirmer via POST avec CSRF
    if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        $_SESSION['flash_message'] = "ID de médecin invalide ou manquant.";
        $_SESSION['flash_type'] = "error";
        header("Location: " . $redirect_page_val_med);
        exit;
    }
    // Vous pourriez afficher une page de confirmation ici, ou juste rediriger si vous gardez le GET
    // Pour l'instant, on va supposer que la validation se fait directement si on arrive avec GET (moins sécurisé)
    // Ou mieux, on force POST. Modifions pour utiliser POST :
    $_SESSION['flash_message'] = "Action non autorisée (GET). Veuillez utiliser le bouton approprié.";
    $_SESSION['flash_type'] = "warning";
    header("Location: " . $redirect_page_val_med);
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $_SESSION['flash_message'] = "Erreur de sécurité. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $redirect_page_val_med);
    exit;
}
$medecin_id_a_valider = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);

if (!$medecin_id_a_valider) {
    $_SESSION['flash_message'] = "ID de médecin invalide ou manquant pour la validation.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $redirect_page_val_med);
    exit;
}


try {
    if (!$pdo->query("SHOW TABLES LIKE 'medecins'")->rowCount() > 0) {
        throw new PDOException("La table 'medecins' n'existe pas.");
    }

    $stmt_check_med_val = $pdo->prepare("SELECT email, nom, prenom, valide FROM medecins WHERE id = ?");
    $stmt_check_med_val->execute([$medecin_id_a_valider]);
    $medecin_data_to_validate = $stmt_check_med_val->fetch(PDO::FETCH_ASSOC);

    if (!$medecin_data_to_validate) {
        $_SESSION['flash_message'] = "Médecin (ID: $medecin_id_a_valider) introuvable.";
        $_SESSION['flash_type'] = "warning";
        header("Location: " . $redirect_page_val_med);
        exit;
    }

    if ($medecin_data_to_validate['valide'] == 1) {
        $_SESSION['flash_message'] = "Ce médecin (Dr. " . htmlspecialchars($medecin_data_to_validate['prenom'] . ' ' . $medecin_data_to_validate['nom']) . ") est déjà validé.";
        $_SESSION['flash_type'] = "info";
        header("Location: " . $redirect_page_val_med);
        exit;
    }
    
    $stmt_update_med_status = $pdo->prepare("UPDATE medecins SET valide = 1 WHERE id = ? AND valide = 0");
    
    if ($stmt_update_med_status->execute([$medecin_id_a_valider])) {
        if ($stmt_update_med_status->rowCount() > 0) {
            // Journalisation de l'action
            log_action_application(
                $pdo,
                'VALIDATION_MEDECIN',
                "Le médecin Dr. " . htmlspecialchars($medecin_data_to_validate['prenom'] . ' ' . $medecin_data_to_validate['nom']) . " (ID: $medecin_id_a_valider) a été validé.",
                $medecin_id_a_valider,
                'medecin',
                ['email_medecin' => $medecin_data_to_validate['email']]
            );

            $_SESSION['flash_message'] = "Le médecin (Dr. " . htmlspecialchars($medecin_data_to_validate['prenom'] . ' ' . $medecin_data_to_validate['nom']) . ") a été validé avec succès.";
            $_SESSION['flash_type'] = "success";

            if (function_exists('envoyer_email') && function_exists('get_email_html_layout') && !empty($medecin_data_to_validate['email'])) {
                $nom_medecin_email_val = "Dr. " . htmlspecialchars($medecin_data_to_validate['prenom'] . ' ' . $medecin_data_to_validate['nom']);
                $sujet_email_validation_med = "Votre compte SANTE TV a été validé !";
                
                $protocol_email_val = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $host_email_val = $_SERVER['HTTP_HOST'];
                $base_path_email_val = dirname(dirname($_SERVER['PHP_SELF'])); // php/ -> ../
                if (basename($base_path_email_val) === 'admin') $base_path_email_val = dirname($base_path_email_val); // admin/ -> ../../
                if ($base_path_email_val === '.' || $base_path_email_val === '/' || $base_path_email_val === '\\') $base_path_email_val = '';
                
                $lien_connexion_email_val = $protocol_email_val . $host_email_val . $base_path_email_val . "/pages/connexion.php"; 

                $contenu_principal_email_med = "<p>Bonjour " . $nom_medecin_email_val . ",</p><p>Bonne nouvelle ! Votre demande d'inscription sur la plateforme SANTE TV a été examinée et <strong>approuvée</strong> par notre équipe d'administration.</p><p>Votre compte médecin est maintenant actif. Vous pouvez dès à présent vous connecter à votre espace personnel pour :</p><ul style='list-style-type: disc; margin-left: 20px; padding-left: 5px;'><li>Compléter et gérer votre profil professionnel public.</li><li>Définir vos plages de disponibilité pour les consultations.</li><li>Commencer à recevoir et gérer les demandes de rendez-vous de patients.</li></ul><div class='button-container'><a href='" . $lien_connexion_email_val . "' class='button'>Accéder à Mon Espace Médecin</a></div><p>Si vous avez des questions ou besoin d'assistance pour démarrer, n'hésitez pas à consulter notre section FAQ ou à contacter notre support.</p><p>Nous sommes ravis de vous accueillir au sein de la communauté SANTE TV !</p><p>Cordialement,<br>L'équipe d'Administration SANTE TV</p>";
                $corps_html_email_validation_med = get_email_html_layout($sujet_email_validation_med, $contenu_principal_email_med, "SANTE TV");
                envoyer_email($medecin_data_to_validate['email'], $nom_medecin_email_val, $sujet_email_validation_med, $corps_html_email_validation_med);
            }
        } else {
            $_SESSION['flash_message'] = "Le médecin (ID: $medecin_id_a_valider) était déjà validé ou une erreur est survenue.";
            $_SESSION['flash_type'] = "info";
        }
    } else {
        $_SESSION['flash_message'] = "Erreur lors de la tentative de validation du médecin en base de données.";
        $_SESSION['flash_type'] = "error";
    }

} catch (PDOException $e) {
    error_log("Erreur PDO dans valider_medecin.php (Médecin ID: $medecin_id_a_valider): " . $e->getMessage());
    $_SESSION['flash_message'] = "Une erreur technique est survenue lors de la validation.";
    $_SESSION['flash_type'] = "error";
}

header("Location: " . $redirect_page_val_med);
exit;
?>