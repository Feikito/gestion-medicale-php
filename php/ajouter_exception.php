<?php
session_start(); 
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 

// 1. Sécurité : Vérifier médecin connecté et validé
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'medecin') {
    $_SESSION['flash_message_login'] = "Accès non autorisé. Veuillez vous connecter.";
    $_SESSION['flash_type_login'] = "warning";
    header('Location: ../pages/connexion.php');
    exit;
}
$medecin_id_add_exception = $_SESSION['utilisateur_id'];

$stmt_check_med_valid_exc = $pdo->prepare("SELECT valide FROM medecins WHERE id = ?");
$stmt_check_med_valid_exc->execute([$medecin_id_add_exception]);
$medecin_data_valid_exc = $stmt_check_med_valid_exc->fetch();

if (!$medecin_data_valid_exc || $medecin_data_valid_exc['valide'] != 1) {
    $_SESSION['flash_message'] = "Votre compte médecin doit être validé pour gérer vos exceptions d'horaires.";
    $_SESSION['flash_type'] = "warning";
    header('Location: gestion_disponibilites_medecin.php'); 
    exit;
}

$form_origin_add_exception = $_POST['form_origin_exception'] ?? 'gestion_disponibilites_medecin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $form_origin_add_exception);
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $_SESSION['flash_message'] = "Erreur de sécurité lors de l'ajout de l'exception. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    $_SESSION['form_data_exception'] = $_POST; 
    header("Location: " . $form_origin_add_exception . "#formAddException"); 
    exit;
}

$date_exception_str_add = trim($_POST['date_exception'] ?? '');
$type_exception_add = trim($_POST['type_exception'] ?? '');
$heure_debut_form_add_exc = trim($_POST['heure_debut_exception'] ?? ''); 
$heure_fin_form_add_exc = trim($_POST['heure_fin_exception'] ?? '');   
$motif_add_exc = !empty(trim($_POST['motif'] ?? '')) ? htmlspecialchars(trim($_POST['motif']), ENT_QUOTES, 'UTF-8') : null;

$_SESSION['form_data_exception'] = $_POST; 
$_SESSION['form_errors_exception'] = []; 
$errors_add_exception = &$_SESSION['form_errors_exception']; 

$date_exception_obj_add = null;
if (empty($date_exception_str_add)) {
    $errors_add_exception['date_exception'] = "La date de l'exception est requise.";
} else {
    try {
        $date_exception_obj_add = new DateTime($date_exception_str_add);
        if ($date_exception_obj_add->format('Y-m-d') !== $date_exception_str_add) {
            throw new Exception("Format de date incorrect.");
        }
        $aujourdhui_minuit_add_exc = new DateTime('today');
        if ($date_exception_obj_add < $aujourdhui_minuit_add_exc) {
            $errors_add_exception['date_exception'] = "La date de l'exception ne peut pas être dans le passé.";
        }
    } catch (Exception $e) {
        $errors_add_exception['date_exception'] = "Format de date d'exception invalide (AAAA-MM-JJ attendu).";
    }
}

// Assurez-vous que ces types correspondent à l'ENUM dans votre BDD
$valid_exception_types = ['non_travaille', 'indisponible', 'travail_exceptionnel', 'pause_exceptionnelle'];
if (empty($type_exception_add) || !in_array($type_exception_add, $valid_exception_types)) {
    $errors_add_exception['type_exception'] = "Veuillez sélectionner un type d'exception valide.";
}

$heure_debut_db_format_add_exc = null;
$heure_fin_db_format_add_exc = null;
$heures_requises_pour_type = in_array($type_exception_add, ['indisponible', 'travail_exceptionnel', 'pause_exceptionnelle']);
$heures_fournies = !empty($heure_debut_form_add_exc) || !empty($heure_fin_form_add_exc);

if ($heures_requises_pour_type || $heures_fournies) {
    if (empty($heure_debut_form_add_exc) || !preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $heure_debut_form_add_exc)) {
        if ($heures_requises_pour_type) $errors_add_exception['heure_debut_exception'] = "L'heure de début est requise (Format HH:MM).";
        elseif ($heures_fournies && empty($heure_debut_form_add_exc)) $errors_add_exception['heure_debut_exception'] = "L'heure de début est requise si une heure de fin est fournie.";
    } else {
        $heure_debut_db_format_add_exc = $heure_debut_form_add_exc . ':00';
    }

    if (empty($heure_fin_form_add_exc) || !preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $heure_fin_form_add_exc)) {
         if ($heures_requises_pour_type) $errors_add_exception['heure_fin_exception'] = "L'heure de fin est requise (Format HH:MM).";
         elseif ($heures_fournies && empty($heure_fin_form_add_exc)) $errors_add_exception['heure_fin_exception'] = "L'heure de fin est requise si une heure de début est fournie.";
    } else {
        $heure_fin_db_format_add_exc = $heure_fin_form_add_exc . ':00';
    }

    if (empty($errors_add_exception['heure_debut_exception']) && empty($errors_add_exception['heure_fin_exception']) && $heure_debut_db_format_add_exc && $heure_fin_db_format_add_exc) {
        if (strtotime($heure_debut_form_add_exc) >= strtotime($heure_fin_form_add_exc)) {
            $errors_add_exception['time_order_exception'] = "L'heure de fin doit être postérieure à l'heure de début.";
        }
    }
} else if ($type_exception_add === 'non_travaille') {
    // Ok, heures non requises pour journée entière
}

// Vérification de non-chevauchement (simplifiée)
if (empty($errors_add_exception) && $date_exception_obj_add) {
    // S'assurer que la table exceptions_horaires_medecin existe
    $table_exceptions_exist_check = $pdo->query("SHOW TABLES LIKE 'exceptions_horaires_medecin'")->rowCount() > 0;
    if ($table_exceptions_exist_check) {
        if ($type_exception_add === 'non_travaille' && $heure_debut_db_format_add_exc === null && $heure_fin_db_format_add_exc === null) {
            $stmt_check_full_day_off_exc = $pdo->prepare(
                "SELECT id FROM exceptions_horaires_medecin 
                 WHERE medecin_id = :med_id AND date_exception = :date_exc 
                 AND type_exception = 'non_travaille' AND heure_debut IS NULL AND heure_fin IS NULL"
            );
            $stmt_check_full_day_off_exc->execute([':med_id' => $medecin_id_add_exception, ':date_exc' => $date_exception_str_add]);
            if ($stmt_check_full_day_off_exc->fetch()) {
                $errors_add_exception['date_exception'] = "Une exception 'Journée Non Travaillée' existe déjà pour le " . $date_exception_obj_add->format('d/m/Y') . ".";
            }
        }
        // TODO: Ajouter une logique de vérification de chevauchement plus fine pour les plages horaires spécifiques si nécessaire.
    } else {
        error_log("Table 'exceptions_horaires_medecin' non trouvée lors de l'ajout d'exception.");
        $errors_add_exception['_general'] = "Erreur de configuration de la base de données (exceptions).";
    }
}


if (!empty($errors_add_exception)) {
    $_SESSION['flash_message'] = $errors_add_exception['_general'] ?? "Des erreurs ont été détectées. Veuillez corriger.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $form_origin_add_exception . "#formAddException");
    exit;
}

try {
    // S'assurer à nouveau que la table existe avant l'insertion
    if (!$pdo->query("SHOW TABLES LIKE 'exceptions_horaires_medecin'")->rowCount() > 0) {
        throw new PDOException("La table 'exceptions_horaires_medecin' n'existe pas.");
    }

    $sql_insert_exception = "INSERT INTO exceptions_horaires_medecin 
                               (medecin_id, date_exception, heure_debut, heure_fin, type_exception, motif) 
                             VALUES 
                               (:medecin_id, :date_exception, :heure_debut, :heure_fin, :type_exception, :motif)";
    $stmt_insert_exception = $pdo->prepare($sql_insert_exception);
    
    $stmt_insert_exception->execute([
        ':medecin_id' => $medecin_id_add_exception,
        ':date_exception' => $date_exception_str_add,
        ':heure_debut' => $heure_debut_db_format_add_exc, 
        ':heure_fin' => $heure_fin_db_format_add_exc,     
        ':type_exception' => $type_exception_add,
        ':motif' => $motif_add_exc 
    ]);

    unset($_SESSION['form_data_exception']); 
    unset($_SESSION['form_errors_exception']);

    $_SESSION['flash_message'] = "Nouvelle exception d'horaire pour le " . ($date_exception_obj_add ? $date_exception_obj_add->format('d/m/Y') : '') . " ajoutée.";
    $_SESSION['flash_type'] = "success";
    header("Location: " . $form_origin_add_exception);
    exit;

} catch (PDOException $e) {
    error_log("Erreur PDO ajouter_exception.php (Médecin ID: $medecin_id_add_exception): " . $e->getMessage());
    $error_message_pdo = "Une erreur technique est survenue lors de l'ajout de l'exception.";
    if ($e->getCode() == 23000) { 
        $error_message_pdo = "Une exception similaire (même date/heure/type) existe déjà.";
        $errors_add_exception['_general'] = "Conflit avec une exception existante."; // Pour affichage spécifique si besoin
        $_SESSION['form_errors_exception'] = $errors_add_exception;
    }
    $_SESSION['flash_message'] = $error_message_pdo;
    $_SESSION['flash_type'] = "error";
    $_SESSION['form_data_exception'] = $_POST; 
    header("Location: " . $form_origin_add_exception . "#formAddException");
    exit;
}
?>