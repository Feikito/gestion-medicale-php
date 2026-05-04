<?php
session_start(); 
require 'db.php'; 
require_once __DIR__ . '/utils/email_functions.php'; 
require_once __DIR__ . '/utils/email_template.php';

if (!isset($_SESSION['admin_id'])) { 
    $_SESSION['flash_message_login'] = "Accès refusé.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/admin-login.php'); 
    exit;
}

$default_redirect_page_inval_med = '../admin/gestion_medecins.php'; 
$return_url_inval_med = trim($_GET['return_url'] ?? '');
$redirect_page_inval_med = $default_redirect_page_inval_med; 
if (!empty($return_url_inval_med)) {
    $parsed_return_url_inval = parse_url($return_url_inval_med);
    $allowed_paths_inval = ['gestion_medecins.php', 'voir_medecin.php', 'dashboard_admin.php'];
    $path_is_allowed_inval = false;
    if (isset($parsed_return_url_inval['path'])) {
        foreach ($allowed_paths_inval as $allowed_path_inval) {
            if (strpos(basename($parsed_return_url_inval['path']), $allowed_path_inval) !== false) {
                $path_is_allowed_inval = true; break;
            }
        }
    }
    if ($path_is_allowed_inval) {
        $redirect_page_inval_med = basename($parsed_return_url_inval['path']);
        if (isset($parsed_return_url_inval['query'])) {
            $redirect_page_inval_med .= '?' . $parsed_return_url_inval['query'];
        }
    }
}
if (strpos($redirect_page_inval_med, "admin/") !== 0 && $redirect_page_inval_med !== $default_redirect_page_inval_med) {
    $redirect_page_inval_med = "../admin/" . $redirect_page_inval_med;
}


if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
    $_SESSION['flash_message'] = "ID de médecin invalide ou manquant pour l'invalidation.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $redirect_page_inval_med);
    exit;
}
$medecin_id_a_invalider = (int)$_GET['id'];

try {
    if (!$pdo->query("SHOW TABLES LIKE 'medecins'")->rowCount() > 0) {
        throw new PDOException("La table 'medecins' n'existe pas.");
    }

    $stmt_check_med_inval = $pdo->prepare("SELECT email, nom, prenom, valide FROM medecins WHERE id = ?");
    $stmt_check_med_inval->execute([$medecin_id_a_invalider]);
    $medecin_to_invalidate_data = $stmt_check_med_inval->fetch(PDO::FETCH_ASSOC);

    if (!$medecin_to_invalidate_data) {
        $_SESSION['flash_message'] = "Médecin (ID: $medecin_id_a_invalider) introuvable.";
        $_SESSION['flash_type'] = "warning";
        header("Location: " . $redirect_page_inval_med);
        exit;
    }

    if ($medecin_to_invalidate_data['valide'] != 1) { 
        $_SESSION['flash_message'] = "Ce médecin (Dr. " . htmlspecialchars($medecin_to_invalidate_data['prenom'] . ' ' . $medecin_to_invalidate_data['nom']) . ") n'est pas actif. Action non applicable.";
        $_SESSION['flash_type'] = "info";
        header("Location: " . $redirect_page_inval_med);
        exit;
    }
    $nom_medecin_inval_email_notify = "Dr. " . htmlspecialchars($medecin_to_invalidate_data['prenom'] . ' ' . $medecin_to_invalidate_data['nom']);

    $stmt_update_med_inval_status = $pdo->prepare("UPDATE medecins SET valide = 0 WHERE id = ? AND valide = 1");
    
    if ($stmt_update_med_inval_status->execute([$medecin_id_a_invalider])) {
        if ($stmt_update_med_inval_status->rowCount() > 0) {
            $_SESSION['flash_message'] = "Compte du médecin " . $nom_medecin_inval_email_notify . " (ID: $medecin_id_a_invalider) rendu inactif.";
            $_SESSION['flash_type'] = "success";

            if (function_exists('envoyer_email') && function_exists('get_email_html_layout') && !empty($medecin_to_invalidate_data['email'])) {
                $sujet_email_invalidation_med_notify = "Information importante concernant votre compte SANTE TV";
                $email_support_inval = defined('CONTACT_FORM_RECIPIENT_EMAIL') ? CONTACT_FORM_RECIPIENT_EMAIL : 'contact@santetv.ma'; // Utiliser l'email de contact défini
                
                $contenu_principal_email_inval = "
                    <p>Bonjour " . $nom_medecin_inval_email_notify . ",</p>
                    <p>Nous vous informons que votre compte médecin sur la plateforme SANTE TV a été temporairement désactivé par un administrateur.</p>
                    <p>En conséquence, votre profil n'est plus visible publiquement et vous ne pourrez plus recevoir de nouvelles demandes de rendez-vous tant que votre compte reste inactif.</p>
                    <p>Vos rendez-vous déjà confirmés avec des patients restent planifiés. Nous vous conseillons de vérifier votre agenda et de contacter les patients concernés si des ajustements sont nécessaires de votre part.</p>
                    <p>Pour toute question, pour comprendre les raisons de cette désactivation ou pour discuter de la réactivation de votre compte, veuillez contacter notre équipe de support à l'adresse <a href='mailto:" . htmlspecialchars($email_support_inval) . "'>" . htmlspecialchars($email_support_inval) . "</a> en mentionnant votre ID de compte : {$medecin_id_a_invalider}.</p>
                    <p>Nous espérons pouvoir réactiver rapidement votre compte après clarification.</p>
                    <p>Cordialement,<br>L'équipe d'Administration SANTE TV</p>";
                
                $corps_html_email_invalidation_med_notify = get_email_html_layout($sujet_email_invalidation_med_notify, $contenu_principal_email_inval, "SANTE TV");
                
                envoyer_email(
                    $medecin_to_invalidate_data['email'], 
                    $nom_medecin_inval_email_notify, 
                    $sujet_email_invalidation_med_notify, 
                    $corps_html_email_invalidation_med_notify
                );
            }
        } else {
            $_SESSION['flash_message'] = "Statut du médecin (ID: $medecin_id_a_invalider) non modifié (déjà à jour ?).";
            $_SESSION['flash_type'] = "warning";
        }
    } else {
        $_SESSION['flash_message'] = "Erreur lors de la tentative d'invalidation du médecin.";
        $_SESSION['flash_type'] = "error";
    }

} catch (PDOException $e) {
    error_log("Erreur PDO dans invalider_medecin.php (ID: $medecin_id_a_invalider): " . $e->getMessage());
    $_SESSION['flash_message'] = "Erreur technique lors de l'invalidation."; 
    $_SESSION['flash_type'] = "error";
}

header("Location: " . $redirect_page_inval_med);
exit;
?>