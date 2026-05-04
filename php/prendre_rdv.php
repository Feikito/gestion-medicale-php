<?php
session_start(); 
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 
require_once __DIR__ . '/utils/email_functions.php'; 
require_once __DIR__ . '/utils/email_template.php'; 

if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'patient') {
    $_SESSION['flash_message_login'] = "Vous devez être connecté en tant que patient pour prendre un rendez-vous.";
    $_SESSION['flash_type_login'] = "warning"; 
    
    $redirect_url = $_POST['form_origin_rdv'] ?? '../pages/rendez-vous.php';
    $query_params = [];
    if(isset($_POST['medecin_id'])) $query_params['medecin_id'] = $_POST['medecin_id'];
    if(isset($_POST['date_rdv'])) $query_params['date'] = $_POST['date_rdv'];
    if(isset($_POST['medecin_nom'])) $query_params['medecin_nom'] = $_POST['medecin_nom'];
    
    if (!empty($query_params)) {
        $redirect_url .= (strpos($redirect_url, '?') === false ? '?' : '&') . http_build_query($query_params);
    }
    $_SESSION['redirect_url_after_login'] = $redirect_url;

    header('Location: ../pages/connexion.php'); 
    exit;
}
$patient_id_session_rdv = $_SESSION['utilisateur_id'];

$form_origin_page_rdv = $_POST['form_origin_rdv'] ?? '../pages/rendez-vous.php';
if (strpos($form_origin_page_rdv, 'pages/') === false) { // S'assurer que ../ est présent si pas déjà dans pages
    $form_origin_page_rdv = '../pages/' . ltrim($form_origin_page_rdv, './');
}


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('Location: ' . $form_origin_page_rdv);
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $_SESSION['flash_message'] = "Erreur de sécurité lors de la prise de rendez-vous. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    $_SESSION['form_data_rdv'] = $_POST; 
    header("Location: " . $form_origin_page_rdv);
    exit;
}

$medecin_id_rdv = filter_input(INPUT_POST, 'medecin_id', FILTER_VALIDATE_INT);
$date_rdv_str_rdv = trim($_POST['date_rdv'] ?? '');
$heure_rdv_str_full_rdv = trim($_POST['heure_rdv'] ?? ''); 
$motif_rdv_patient = trim(strip_tags($_POST['motif_rdv'] ?? '')); // Nouveau champ pour le motif

$_SESSION['form_data_rdv'] = $_POST; 
$_SESSION['form_errors_rdv'] = []; 
$errors_rdv_submit = &$_SESSION['form_errors_rdv']; 

$medecin_info_rdv = null; 
if (!$medecin_id_rdv) {
    $errors_rdv_submit['medecin_id'] = "Veuillez sélectionner un médecin valide.";
} else {
    $stmt_check_med_rdv = $pdo->prepare("SELECT id, nom, prenom, email FROM medecins WHERE id = ? AND valide = 1");
    $stmt_check_med_rdv->execute([$medecin_id_rdv]);
    $medecin_info_rdv = $stmt_check_med_rdv->fetch(PDO::FETCH_ASSOC);
    if (!$medecin_info_rdv) {
        $errors_rdv_submit['medecin_id'] = "Le médecin sélectionné n'est pas disponible ou n'existe pas.";
    }
}

$date_rdv_obj_rdv = null;
if (empty($date_rdv_str_rdv)) {
    $errors_rdv_submit['date_rdv'] = "Veuillez sélectionner une date pour le rendez-vous.";
} else {
    try {
        $date_rdv_obj_rdv = new DateTime($date_rdv_str_rdv);
        if ($date_rdv_obj_rdv->format('Y-m-d') !== $date_rdv_str_rdv) {
            throw new Exception("Format de date incorrect.");
        }
        $aujourdhui_minuit_rdv = new DateTime('today');
        if ($date_rdv_obj_rdv < $aujourdhui_minuit_rdv) {
            $errors_rdv_submit['date_rdv'] = "La date du rendez-vous ne peut pas être dans le passé.";
        }
        $limite_futur_rdv = (new DateTime('today'))->modify('+3 months');
        if ($date_rdv_obj_rdv > $limite_futur_rdv) {
            $errors_rdv_submit['date_rdv'] = "Vous ne pouvez pas prendre de rendez-vous plus de 3 mois à l'avance.";
        }
    } catch (Exception $e) {
        $errors_rdv_submit['date_rdv'] = "Format de date invalide (AAAA-MM-JJ attendu).";
    }
}

if (empty($heure_rdv_str_full_rdv) || !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $heure_rdv_str_full_rdv)) {
    $errors_rdv_submit['heure_rdv'] = "Veuillez sélectionner un créneau horaire valide.";
}
if (!empty($motif_rdv_patient) && strlen($motif_rdv_patient) > 1000) {
    $errors_rdv_submit['motif_rdv'] = "Le motif du rendez-vous est trop long (max 1000 caractères).";
}


if (!empty($errors_rdv_submit)) {
    $_SESSION['flash_message'] = "Votre demande de rendez-vous contient des erreurs. Veuillez vérifier les champs.";
    $_SESSION['flash_type'] = "error";
    
    $redirect_params_error_query = [];
    if ($medecin_id_rdv) $redirect_params_error_query['medecin_id'] = $medecin_id_rdv;
    if ($date_rdv_str_rdv) $redirect_params_error_query['date'] = $date_rdv_str_rdv;
    if (isset($_POST['medecin_nom'])) $redirect_params_error_query['medecin_nom'] = $_POST['medecin_nom'];
    $redirect_url_error = $form_origin_page_rdv . (empty($redirect_params_error_query) ? '' : (strpos($form_origin_page_rdv, '?') === false ? '?' : '&') . http_build_query($redirect_params_error_query));
    
    header("Location: " . $redirect_url_error);
    exit;
}

try {
    $stmt_check_slot_taken = $pdo->prepare(
        "SELECT COUNT(*) FROM rendez_vous 
         WHERE medecin_id = :medecin_id AND date_rdv = :date_rdv AND heure_rdv = :heure_rdv 
         AND statut IN ('en attente', 'confirmé')"
    );
    $stmt_check_slot_taken->execute([
        ':medecin_id' => $medecin_id_rdv,
        ':date_rdv' => $date_rdv_str_rdv,
        ':heure_rdv' => $heure_rdv_str_full_rdv 
    ]);
    if ($stmt_check_slot_taken->fetchColumn() > 0) {
        $errors_rdv_submit['heure_rdv'] = "Désolé, ce créneau horaire (" . substr($heure_rdv_str_full_rdv,0,5) . ") n'est plus disponible pour le " . date('d/m/Y', strtotime($date_rdv_str_rdv)) . ". Veuillez en choisir un autre.";
    }
} catch (PDOException $e) {
    error_log("Erreur PDO vérification créneau (prendre_rdv.php): " . $e->getMessage());
    $errors_rdv_submit['_general'] = "Erreur technique lors de la vérification de la disponibilité du créneau.";
}

if (!empty($errors_rdv_submit)) {
    $_SESSION['flash_message'] = $errors_rdv_submit['heure_rdv'] ?? $errors_rdv_submit['_general'] ?? "Erreur de validation du créneau.";
    $_SESSION['flash_type'] = "error";

    $redirect_params_error_query = [];
    if ($medecin_id_rdv) $redirect_params_error_query['medecin_id'] = $medecin_id_rdv;
    if ($date_rdv_str_rdv) $redirect_params_error_query['date'] = $date_rdv_str_rdv;
    if (isset($_POST['medecin_nom'])) $redirect_params_error_query['medecin_nom'] = $_POST['medecin_nom'];
    $redirect_url_error = $form_origin_page_rdv . (empty($redirect_params_error_query) ? '' : (strpos($form_origin_page_rdv, '?') === false ? '?' : '&') . http_build_query($redirect_params_error_query));
    
    header("Location: " . $redirect_url_error);
    exit;
}

try {
    $sql_insert_rdv = "INSERT INTO rendez_vous (patient_id, medecin_id, date_rdv, heure_rdv, motif_rdv, created_at) 
                       VALUES (:patient_id, :medecin_id, :date_rdv, :heure_rdv, :motif_rdv, NOW())";
    $stmt_insert_rdv = $pdo->prepare($sql_insert_rdv);
    
    $stmt_insert_rdv->execute([
        ':patient_id' => $patient_id_session_rdv,
        ':medecin_id' => $medecin_id_rdv,
        ':date_rdv' => $date_rdv_str_rdv,
        ':heure_rdv' => $heure_rdv_str_full_rdv,
        ':motif_rdv' => !empty($motif_rdv_patient) ? $motif_rdv_patient : null
    ]);
    $new_rdv_id = $pdo->lastInsertId();

    unset($_SESSION['form_data_rdv']); 
    unset($_SESSION['form_errors_rdv']);

    if ($medecin_info_rdv && function_exists('envoyer_email') && function_exists('get_email_html_layout')) {
        $nom_patient_pour_email = $_SESSION['nom'] ?? 'Un patient'; 
        $nom_medecin_pour_email = "Dr. " . htmlspecialchars($medecin_info_rdv['prenom'] . ' ' . $medecin_info_rdv['nom']);
        $sujet_medecin = "Nouvelle demande de Rendez-vous sur SANTE TV";
        
        $contenu_principal_email = "
            <p>Bonjour " . $nom_medecin_pour_email . ",</p>
            <p>Vous avez une nouvelle demande de rendez-vous de la part de <strong>" . htmlspecialchars($nom_patient_pour_email) . "</strong>.</p>
            <p><strong>Détails de la demande :</strong></p>
            <ul style='list-style-type: disc; margin-left: 20px; padding-left: 5px;'>
                <li><strong>Date :</strong> " . date('d/m/Y', strtotime($date_rdv_str_rdv)) . "</li>
                <li><strong>Heure :</strong> " . substr($heure_rdv_str_full_rdv, 0, 5) . "</li>";
        if(!empty($motif_rdv_patient)){
            $contenu_principal_email .= "<li><strong>Motif fourni par le patient :</strong> " . nl2br(htmlspecialchars($motif_rdv_patient)) . "</li>";
        }
        $contenu_principal_email .= "</ul>
            <p>Veuillez vous connecter à votre espace médecin pour accepter ou refuser cette demande.</p>
            <div class='button-container'>
                 <a href='". $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) ."/mes_rendez_vous_medecin.php?statut=en%20attente' class='button'>Gérer mes demandes</a>
            </div>
            <p>Cordialement,<br>L'équipe SANTE TV</p>";
        
        $corps_html_medecin = get_email_html_layout($sujet_medecin, $contenu_principal_email, "SANTE TV - Notification");
        envoyer_email($medecin_info_rdv['email'], $nom_medecin_pour_email, $sujet_medecin, $corps_html_medecin);
    }

    $_SESSION['flash_message'] = "Votre demande de rendez-vous (ID: #$new_rdv_id) a été envoyée avec succès ! Vous serez notifié(e) de sa confirmation par le médecin.";
    $_SESSION['flash_type'] = "success";
    header('Location: mes_rendez_vous_patient.php?new_rdv_id=' . $new_rdv_id); 
    exit;

} catch (PDOException $e) {
    error_log("Erreur PDO insertion RDV (Patient ID: $patient_id_session_rdv, Medecin ID: $medecin_id_rdv): " . $e->getMessage());
    $_SESSION['flash_message'] = "Une erreur technique est survenue lors de la finalisation de votre rendez-vous. Veuillez réessayer.";
    $_SESSION['flash_type'] = "error";
    $_SESSION['form_data_rdv'] = $_POST; 

    $redirect_params_error_query = [];
    if ($medecin_id_rdv) $redirect_params_error_query['medecin_id'] = $medecin_id_rdv;
    if ($date_rdv_str_rdv) $redirect_params_error_query['date'] = $date_rdv_str_rdv;
    if (isset($_POST['medecin_nom'])) $redirect_params_error_query['medecin_nom'] = $_POST['medecin_nom'];
    $redirect_url_error = $form_origin_page_rdv . (empty($redirect_params_error_query) ? '' : (strpos($form_origin_page_rdv, '?') === false ? '?' : '&') . http_build_query($redirect_params_error_query));

    header("Location: " . $redirect_url_error);
    exit;
}
?>