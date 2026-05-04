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

$default_redirect_page_ref_med = '../admin/gestion_medecins.php'; 
$return_url_ref_med = trim($_GET['return_url'] ?? '');
$redirect_page_ref_med = $default_redirect_page_ref_med; 
if (!empty($return_url_ref_med)) {
    $parsed_return_url_ref = parse_url($return_url_ref_med);
    $allowed_paths_ref = ['gestion_medecins.php', 'dashboard_admin.php']; 
    $path_is_allowed_ref = false;
    if (isset($parsed_return_url_ref['path'])) {
        foreach ($allowed_paths_ref as $allowed_path_ref) {
            if (strpos(basename($parsed_return_url_ref['path']), $allowed_path_ref) !== false) {
                $path_is_allowed_ref = true; break;
            }
        }
    }
    if ($path_is_allowed_ref) {
        $redirect_page_ref_med = basename($parsed_return_url_ref['path']); 
        if (isset($parsed_return_url_ref['query'])) {
            $redirect_page_ref_med .= '?' . $parsed_return_url_ref['query'];
        }
    }
}
if (strpos($redirect_page_ref_med, "admin/") !== 0 && $redirect_page_ref_med !== $default_redirect_page_ref_med) {
    $redirect_page_ref_med = "../admin/" . $redirect_page_ref_med;
}


if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
    $_SESSION['flash_message'] = "ID de médecin invalide ou manquant pour le refus.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $redirect_page_ref_med);
    exit;
}
$medecin_id_a_refuser = (int)$_GET['id'];

try {
    if (!$pdo->query("SHOW TABLES LIKE 'medecins'")->rowCount() > 0) {
        throw new PDOException("La table 'medecins' n'existe pas.");
    }

    $stmt_med_info_to_refuse = $pdo->prepare(
        "SELECT nom, prenom, email, photo, document_justificatif, valide 
         FROM medecins WHERE id = ?"
    );
    $stmt_med_info_to_refuse->execute([$medecin_id_a_refuser]);
    $medecin_to_refuse_data = $stmt_med_info_to_refuse->fetch(PDO::FETCH_ASSOC);

    if (!$medecin_to_refuse_data) {
        $_SESSION['flash_message'] = "Médecin (ID: $medecin_id_a_refuser) introuvable.";
        $_SESSION['flash_type'] = "warning";
        header("Location: " . $redirect_page_ref_med);
        exit;
    }

    if ($medecin_to_refuse_data['valide'] != 0) {
        $_SESSION['flash_message'] = "Ce médecin (Dr. " . htmlspecialchars($medecin_to_refuse_data['prenom'] . ' ' . $medecin_to_refuse_data['nom']) . ") n'est pas en attente. Action non applicable.";
        $_SESSION['flash_type'] = "info"; 
        header("Location: " . $redirect_page_ref_med);
        exit;
    }

    $photo_path_db_to_delete_ref = $medecin_to_refuse_data['photo'];
    $document_path_db_to_delete_ref = $medecin_to_refuse_data['document_justificatif'];
    $nom_medecin_refuse_email_ref = "Dr. " . htmlspecialchars($medecin_to_refuse_data['prenom'] . ' ' . $medecin_to_refuse_data['nom']);

    $stmt_delete_medecin_ref = $pdo->prepare("DELETE FROM medecins WHERE id = ? AND valide = 0");
    
    if ($stmt_delete_medecin_ref->execute([$medecin_id_a_refuser])) {
        if ($stmt_delete_medecin_ref->rowCount() > 0) {
            if (!empty($photo_path_db_to_delete_ref)) {
                $full_photo_path_del_ref = __DIR__ . '/../' . $photo_path_db_to_delete_ref; 
                if (file_exists($full_photo_path_del_ref)) { @unlink($full_photo_path_del_ref); }
            }
            if (!empty($document_path_db_to_delete_ref)) {
                $full_document_path_del_ref = __DIR__ . '/../' . $document_path_db_to_delete_ref; 
                if (file_exists($full_document_path_del_ref)) { @unlink($full_document_path_del_ref); }
            }
            
            $_SESSION['flash_message'] = "Demande de " . $nom_medecin_refuse_email_ref . " (ID: $medecin_id_a_refuser) refusée et données supprimées.";
            $_SESSION['flash_type'] = "success";

            if (function_exists('envoyer_email') && function_exists('get_email_html_layout') && !empty($medecin_to_refuse_data['email'])) {
                $sujet_email_refus_med_notify = "Suite à votre demande d'inscription sur SANTE TV";
                $contenu_principal_email_refus = "
                    <p>Bonjour " . $nom_medecin_refuse_email_ref . ",</p>
                    <p>Nous vous remercions pour l'intérêt que vous portez à la plateforme SANTE TV.</p>
                    <p>Après examen de votre demande d'inscription, nous sommes au regret de vous informer que nous ne pouvons y donner une suite favorable pour le moment.</p>
                    <p>Si vous souhaitez obtenir plus d'informations ou si vous pensez qu'il s'agit d'une erreur, n'hésitez pas à contacter notre support à l'adresse <a href='mailto:" . htmlspecialchars(CONTACT_FORM_RECIPIENT_EMAIL) . "'>" . htmlspecialchars(CONTACT_FORM_RECIPIENT_EMAIL) . "</a>.</p>
                    <p>Nous vous souhaitons bonne continuation dans vos activités professionnelles.</p>
                    <p>Cordialement,<br>L'équipe d'Administration SANTE TV</p>";
                
                $corps_html_email_refus_med_notify = get_email_html_layout($sujet_email_refus_med_notify, $contenu_principal_email_refus, "SANTE TV");
                
                envoyer_email(
                    $medecin_to_refuse_data['email'], 
                    $nom_medecin_refuse_email_ref, 
                    $sujet_email_refus_med_notify, 
                    $corps_html_email_refus_med_notify
                );
            }

        } else {
             $_SESSION['flash_message'] = "Médecin (ID: $medecin_id_a_refuser) non supprimé (statut modifié ?).";
             $_SESSION['flash_type'] = "warning";
        }
    } else {
        $_SESSION['flash_message'] = "Erreur lors de la suppression du médecin de la BDD.";
        $_SESSION['flash_type'] = "error";
    }

} catch (PDOException $e) {
    error_log("Erreur PDO dans refuser_medecin.php (ID: $medecin_id_a_refuser): " . $e->getMessage());
    $_SESSION['flash_message'] = "Erreur technique lors du refus/suppression.";
    $_SESSION['flash_type'] = "error";
}

header("Location: " . $redirect_page_ref_med);
exit;
?>