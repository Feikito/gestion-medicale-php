<?php
session_start(); 
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 

// 1. Sécurité : Vérifier que l'utilisateur est un médecin connecté et validé
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'medecin') {
    $_SESSION['flash_message_login'] = "Accès non autorisé. Veuillez vous connecter.";
    $_SESSION['flash_type_login'] = "warning";
    header('Location: ../pages/connexion.php');
    exit;
}
$medecin_id_add_dispo = $_SESSION['utilisateur_id'];

$stmt_check_med_valid_dispo = $pdo->prepare("SELECT valide FROM medecins WHERE id = ?");
$stmt_check_med_valid_dispo->execute([$medecin_id_add_dispo]);
$medecin_data_valid_dispo = $stmt_check_med_valid_dispo->fetch();

if (!$medecin_data_valid_dispo || $medecin_data_valid_dispo['valide'] != 1) {
    $_SESSION['flash_message'] = "Votre compte médecin doit être validé par un administrateur pour gérer vos disponibilités.";
    $_SESSION['flash_type'] = "warning";
    header('Location: gestion_disponibilites_medecin.php'); 
    exit;
}

// Définition de la map des jours pour le message de succès
$jours_semaine_map_dispo_script = [ // Renommée pour éviter conflit si gestion_disponibilites_medecin.php est inclus
    1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 
    4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 0 => 'Dimanche'
];

$form_origin_add_dispo = $_POST['form_origin_dispo'] ?? 'gestion_disponibilites_medecin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $form_origin_add_dispo);
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $_SESSION['flash_message'] = "Erreur de sécurité lors de l'ajout de la disponibilité. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $form_origin_add_dispo);
    exit;
}

$jour_semaine_add_dispo = filter_input(INPUT_POST, 'jour_semaine', FILTER_VALIDATE_INT, ["options" => ["min_range" => 0, "max_range" => 6]]);
$heure_debut_form_add_dispo = trim($_POST['heure_debut'] ?? ''); 
$heure_fin_form_add_dispo = trim($_POST['heure_fin'] ?? '');   
$type_plage_add_dispo = trim($_POST['type_plage'] ?? '');   

$_SESSION['form_data_dispo'] = $_POST; 
$_SESSION['form_errors_dispo'] = []; 
$errors_add_dispo = &$_SESSION['form_errors_dispo']; 

if ($jour_semaine_add_dispo === false || $jour_semaine_add_dispo === null) { 
    $errors_add_dispo['jour_semaine'] = "Veuillez sélectionner un jour de la semaine valide.";
}

$heure_debut_db_format_add_dispo = null;
if (empty($heure_debut_form_add_dispo) || !preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $heure_debut_form_add_dispo)) {
    $errors_add_dispo['heure_debut'] = "L'heure de début est requise et doit être au format HH:MM (ex: 09:00).";
} else {
    $heure_debut_db_format_add_dispo = $heure_debut_form_add_dispo . ':00'; 
}

$heure_fin_db_format_add_dispo = null;
if (empty($heure_fin_form_add_dispo) || !preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $heure_fin_form_add_dispo)) {
    $errors_add_dispo['heure_fin'] = "L'heure de fin est requise et doit être au format HH:MM (ex: 17:30).";
} else {
    $heure_fin_db_format_add_dispo = $heure_fin_form_add_dispo . ':00'; 
}

if (empty($type_plage_add_dispo) || !in_array($type_plage_add_dispo, ['travail', 'pause'])) {
    $errors_add_dispo['type_plage'] = "Veuillez sélectionner un type de plage valide (Travail ou Pause).";
}

if (empty($errors_add_dispo['heure_debut']) && empty($errors_add_dispo['heure_fin'])) {
    if (strtotime($heure_debut_form_add_dispo) >= strtotime($heure_fin_form_add_dispo)) {
        $errors_add_dispo['time_order'] = "L'heure de fin doit être strictement postérieure à l'heure de début.";
    }
}

if (empty($errors_add_dispo) && $jour_semaine_add_dispo !== false && $jour_semaine_add_dispo !== null) {
    // S'assurer que la table disponibilites_medecin existe avant de la requêter
    $table_dispo_exists_check = $pdo->query("SHOW TABLES LIKE 'disponibilites_medecin'")->rowCount() > 0;
    if ($table_dispo_exists_check) {
        try {
            $stmt_check_overlap_dispo = $pdo->prepare(
                "SELECT id, type_plage, TIME_FORMAT(heure_debut, '%H:%i') AS h_debut, TIME_FORMAT(heure_fin, '%H:%i') AS h_fin 
                 FROM disponibilites_medecin 
                 WHERE medecin_id = :medecin_id AND jour_semaine = :jour_semaine
                   AND NOT (heure_fin <= :new_heure_debut OR heure_debut >= :new_heure_fin)" 
            );
            $stmt_check_overlap_dispo->execute([
                ':medecin_id' => $medecin_id_add_dispo,
                ':jour_semaine' => $jour_semaine_add_dispo,
                ':new_heure_debut' => $heure_debut_db_format_add_dispo, 
                ':new_heure_fin' => $heure_fin_db_format_add_dispo    
            ]);
            $chevauchements = $stmt_check_overlap_dispo->fetchAll(PDO::FETCH_ASSOC);

            if (count($chevauchements) > 0) {
                $overlap_details = [];
                foreach ($chevauchements as $chev) {
                    $overlap_details[] = "Type: " . ucfirst($chev['type_plage']) . " de " . $chev['h_debut'] . " à " . $chev['h_fin'];
                }
                $errors_add_dispo['overlap'] = "La plage horaire " . htmlspecialchars($heure_debut_form_add_dispo . " - " . $heure_fin_form_add_dispo) . 
                                              " chevauche une ou plusieurs plages existantes pour ce jour : <br>" . implode("<br>", $overlap_details);
            }
        } catch (PDOException $e) {
            error_log("Erreur PDO vérification chevauchement dispo: " . $e->getMessage());
            $errors_add_dispo['_general'] = "Erreur technique lors de la vérification des disponibilités existantes.";
        }
    } elseif (!$table_dispo_exists_check) {
        error_log("Table 'disponibilites_medecin' non trouvée lors de l'ajout de disponibilité.");
        $errors_add_dispo['_general'] = "Erreur de configuration de la base de données.";
    }
}


if (!empty($errors_add_dispo)) {
    $_SESSION['flash_message'] = $errors_add_dispo['_general'] ?? "Des erreurs ont été détectées dans le formulaire. Veuillez corriger les champs.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $form_origin_add_dispo . "#formAddDispoReguliere"); 
    exit;
}

try {
    // S'assurer à nouveau que la table existe avant l'insertion
    if (!$pdo->query("SHOW TABLES LIKE 'disponibilites_medecin'")->rowCount() > 0) {
        throw new PDOException("La table 'disponibilites_medecin' n'existe pas.");
    }

    $sql_insert_dispo = "INSERT INTO disponibilites_medecin 
                           (medecin_id, jour_semaine, heure_debut, heure_fin, type_plage) 
                         VALUES 
                           (:medecin_id, :jour_semaine, :heure_debut, :heure_fin, :type_plage)";
    $stmt_insert_dispo = $pdo->prepare($sql_insert_dispo);
    
    $stmt_insert_dispo->execute([
        ':medecin_id' => $medecin_id_add_dispo,
        ':jour_semaine' => $jour_semaine_add_dispo,
        ':heure_debut' => $heure_debut_db_format_add_dispo, 
        ':heure_fin' => $heure_fin_db_format_add_dispo,   
        ':type_plage' => $type_plage_add_dispo
    ]);

    unset($_SESSION['form_data_dispo']); 
    unset($_SESSION['form_errors_dispo']);

    $_SESSION['flash_message'] = "Nouvelle plage horaire (" . htmlspecialchars(ucfirst($type_plage_add_dispo)) . ") ajoutée avec succès pour le " . htmlspecialchars($jours_semaine_map_dispo_script[$jour_semaine_add_dispo] ?? 'Jour inconnu') . " de " . htmlspecialchars($heure_debut_form_add_dispo) . " à " . htmlspecialchars($heure_fin_form_add_dispo) . ".";
    $_SESSION['flash_type'] = "success";
    header("Location: " . $form_origin_add_dispo);
    exit;

} catch (PDOException $e) {
    error_log("Erreur PDO ajouter_dispo.php (Médecin ID: $medecin_id_add_dispo): " . $e->getMessage());
    $_SESSION['flash_message'] = "Une erreur technique est survenue lors de l'ajout de la plage horaire. Veuillez réessayer.";
    $_SESSION['flash_type'] = "error";
    $_SESSION['form_data_dispo'] = $_POST; 
    header("Location: " . $form_origin_add_dispo . "#formAddDispoReguliere");
    exit;
}
?>