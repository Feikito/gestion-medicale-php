<?php
// php/api_creneaux_disponibles.php

header('Content-Type: application/json');
// En production, remplacez '*' par votre domaine frontend exact.
header("Access-Control-Allow-Origin: *"); 
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { 
    // Gérer les requêtes pre-flight pour CORS
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, OPTIONS"); 
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}"); 
    }
    exit(0); 
}

require 'db.php'; 

define('DUREE_RDV_MINUTES_API', 30); 
define('INTERVALLE_CRENEAU_API', DUREE_RDV_MINUTES_API . ' minutes'); 

if (!isset($_GET['medecin_id']) || !filter_var($_GET['medecin_id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
    http_response_code(400); 
    echo json_encode(['error' => 'Paramètre medecin_id invalide ou manquant.']);
    exit;
}
$medecin_id_api_creneaux = (int)$_GET['medecin_id'];

if (!isset($_GET['date'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètre date manquant.']);
    exit;
}
$date_selectionnee_str_api = trim($_GET['date']);

try {
    $date_selectionnee_obj_api = new DateTime($date_selectionnee_str_api);
    if ($date_selectionnee_obj_api->format('Y-m-d') !== $date_selectionnee_str_api) { 
        throw new Exception("Format de date incorrect (AAAA-MM-JJ attendu).");
    }

    $aujourdhui_minuit_api = new DateTime('today'); 
    if ($date_selectionnee_obj_api < $aujourdhui_minuit_api) {
        echo json_encode([]); 
        exit;
    }

    $limite_futur_api = (new DateTime('today'))->modify('+3 months'); 
    if ($date_selectionnee_obj_api > $limite_futur_api) {
        echo json_encode(['message' => 'Prise de rendez-vous limitée à 3 mois dans le futur.', 'creneaux' => []]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

$jour_semaine_php_api = (int)$date_selectionnee_obj_api->format('w'); 
$creneaux_disponibles_finaux_api = [];

try {
    // 1. Vérifier médecin
    $stmt_check_med_api = $pdo->prepare("SELECT id FROM medecins WHERE id = ? AND valide = 1");
    $stmt_check_med_api->execute([$medecin_id_api_creneaux]);
    if (!$stmt_check_med_api->fetch()) {
        echo json_encode(['message' => 'Médecin non trouvé ou non disponible.', 'creneaux' => []]);
        exit;
    }

    // 2. Récupérer exceptions du jour
    $exceptions_du_jour_api = [];
    $table_exceptions_exists = $pdo->query("SHOW TABLES LIKE 'exceptions_horaires_medecin'")->rowCount() > 0;
    if ($table_exceptions_exists) {
        $stmt_exceptions_api = $pdo->prepare("
            SELECT heure_debut, heure_fin, type_exception 
            FROM exceptions_horaires_medecin
            WHERE medecin_id = :medecin_id AND date_exception = :date_exception
        ");
        $stmt_exceptions_api->execute([
            ':medecin_id' => $medecin_id_api_creneaux,
            ':date_exception' => $date_selectionnee_str_api
        ]);
        $exceptions_du_jour_api = $stmt_exceptions_api->fetchAll(PDO::FETCH_ASSOC);

        foreach ($exceptions_du_jour_api as $ex_api) {
            if ($ex_api['type_exception'] === 'non_travaille' && $ex_api['heure_debut'] === null && $ex_api['heure_fin'] === null) {
                echo json_encode([]); 
                exit;
            }
        }
    }

    // 3. Récupérer plages de TRAVAIL (régulières et exceptionnelles)
    $plages_travail_finales_api = [];
    $table_dispo_exists = $pdo->query("SHOW TABLES LIKE 'disponibilites_medecin'")->rowCount() > 0;
    if ($table_dispo_exists) {
        $stmt_travail_api = $pdo->prepare("
            SELECT heure_debut, heure_fin 
            FROM disponibilites_medecin 
            WHERE medecin_id = :medecin_id AND jour_semaine = :jour_semaine AND type_plage = 'travail'
            ORDER BY heure_debut ASC
        ");
        $stmt_travail_api->execute([':medecin_id' => $medecin_id_api_creneaux, ':jour_semaine' => $jour_semaine_php_api]);
        $plages_travail_finales_api = $stmt_travail_api->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($table_exceptions_exists) { // Ajouter travail_exceptionnel seulement si la table existe
        foreach ($exceptions_du_jour_api as $ex_api) {
            if ($ex_api['type_exception'] === 'travail_exceptionnel' && $ex_api['heure_debut'] !== null && $ex_api['heure_fin'] !== null) {
                $plages_travail_finales_api[] = ['heure_debut' => $ex_api['heure_debut'], 'heure_fin' => $ex_api['heure_fin']];
            }
        }
    }
    if (count($plages_travail_finales_api) > 1) {
        usort($plages_travail_finales_api, function($a, $b) { return strcmp($a['heure_debut'], $b['heure_debut']); });
    }
    // NOTE: La fusion des plages de travail qui se chevauchent n'est pas implémentée ici.

    if (empty($plages_travail_finales_api)) { 
        echo json_encode([]); 
        exit; 
    }

    // 4. Récupérer plages de PAUSE (régulières et exceptionnelles)
    $plages_pause_finales_api = [];
    if ($table_dispo_exists) {
        $stmt_pause_reg_api = $pdo->prepare("SELECT heure_debut, heure_fin FROM disponibilites_medecin WHERE medecin_id = :id AND jour_semaine = :jour AND type_plage = 'pause'");
        $stmt_pause_reg_api->execute([':id' => $medecin_id_api_creneaux, ':jour' => $jour_semaine_php_api]);
        $plages_pause_finales_api = $stmt_pause_reg_api->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($table_exceptions_exists) {
        foreach ($exceptions_du_jour_api as $ex_api) {
            if ($ex_api['type_exception'] === 'pause_exceptionnelle' && $ex_api['heure_debut'] !== null && $ex_api['heure_fin'] !== null) {
                $plages_pause_finales_api[] = ['heure_debut' => $ex_api['heure_debut'], 'heure_fin' => $ex_api['heure_fin']];
            }
        }
    }
    if (count($plages_pause_finales_api) > 1) {
        usort($plages_pause_finales_api, function($a, $b) { return strcmp($a['heure_debut'], $b['heure_debut']); });
    }

    // 5. Récupérer RDV pris
    $stmt_rdv_pris_api = $pdo->prepare("
        SELECT TIME_FORMAT(heure_rdv, '%H:%i') AS heure_rdv_debut 
        FROM rendez_vous 
        WHERE medecin_id = :medecin_id AND date_rdv = :date_rdv AND statut IN ('en attente', 'confirmé')
    ");
    $stmt_rdv_pris_api->execute([':medecin_id' => $medecin_id_api_creneaux, ':date_rdv' => $date_selectionnee_str_api]);
    $rdv_pris_heures_debut_api = $stmt_rdv_pris_api->fetchAll(PDO::FETCH_COLUMN, 0);

    // 6. Générer créneaux potentiels
    $creneaux_potentiels_api = [];
    $heure_debut_generation = new DateTime(); 
    if ($date_selectionnee_obj_api->format('Y-m-d') === $aujourdhui_minuit_api->format('Y-m-d')) {
        $heure_debut_generation->modify('+1 hour'); 
    } else {
        $heure_debut_generation->setTime(0,0,0); 
    }

    foreach ($plages_travail_finales_api as $plage_travail_api) {
        $curseur_temps_api = new DateTime($date_selectionnee_str_api . ' ' . $plage_travail_api['heure_debut']);
        $fin_plage_travail_dt_api = new DateTime($date_selectionnee_str_api . ' ' . $plage_travail_api['heure_fin']);

        while ($curseur_temps_api < $fin_plage_travail_dt_api) {
            $debut_creneau_dt_api = clone $curseur_temps_api;
            $fin_creneau_dt_api = (clone $curseur_temps_api)->modify("+" . INTERVALLE_CRENEAU_API);

            if ($fin_creneau_dt_api <= $fin_plage_travail_dt_api && $debut_creneau_dt_api >= $heure_debut_generation) {
                $creneaux_potentiels_api[] = [
                    'debut_dt' => $debut_creneau_dt_api, 
                    'fin_dt' => $fin_creneau_dt_api     
                ];
            }
            $curseur_temps_api->modify("+" . INTERVALLE_CRENEAU_API);
        }
    }

    // 7. Filtrer créneaux potentiels
    foreach ($creneaux_potentiels_api as $creneau_pot) {
        $heure_debut_formattee_api = $creneau_pot['debut_dt']->format('H:i');
        $est_disponible_final = true;

        if (in_array($heure_debut_formattee_api, $rdv_pris_heures_debut_api)) {
            $est_disponible_final = false;
        }
        
        if ($est_disponible_final) {
            foreach ($plages_pause_finales_api as $pause_api) {
                $debut_pause_dt_api = new DateTime($date_selectionnee_str_api . ' ' . $pause_api['heure_debut']);
                $fin_pause_dt_api = new DateTime($date_selectionnee_str_api . ' ' . $pause_api['heure_fin']);
                if (($creneau_pot['debut_dt'] < $fin_pause_dt_api) && ($creneau_pot['fin_dt'] > $debut_pause_dt_api)) {
                    $est_disponible_final = false; break; 
                }
            }
        }
        
        if ($est_disponible_final && $table_exceptions_exists) {
            foreach ($exceptions_du_jour_api as $ex_api) {
                if (in_array($ex_api['type_exception'], ['indisponible', 'non_travaille']) && $ex_api['heure_debut'] !== null && $ex_api['heure_fin'] !== null) {
                    $debut_exception_dt_api = new DateTime($date_selectionnee_str_api . ' ' . $ex_api['heure_debut']);
                    $fin_exception_dt_api = new DateTime($date_selectionnee_str_api . ' ' . $ex_api['heure_fin']);
                    if (($creneau_pot['debut_dt'] < $fin_exception_dt_api) && ($creneau_pot['fin_dt'] > $debut_exception_dt_api)) {
                        $est_disponible_final = false; break; 
                    }
                }
            }
        }
        
        if ($est_disponible_final) {
            $creneaux_disponibles_finaux_api[] = $heure_debut_formattee_api; 
        }
    }
    
    $creneaux_disponibles_finaux_api = array_values(array_unique($creneaux_disponibles_finaux_api));
    sort($creneaux_disponibles_finaux_api); 

    echo json_encode($creneaux_disponibles_finaux_api);

} catch (Exception $e) { 
    error_log("Erreur API (api_creneaux_disponibles.php): " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    http_response_code(500); 
    echo json_encode(['error' => 'Une erreur est survenue lors de la récupération des créneaux disponibles.']); // Message générique en prod
}
?>