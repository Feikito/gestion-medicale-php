<?php
// php/api_medecins.php

// --- POUR DÉBOGAGE SEULEMENT ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- FIN DÉBOGAGE ---

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

require 'db.php';

$sql_count_api_final = 'Non défini avant try';
$sql_medecins_api_final = 'Non défini avant try';
$params_for_count_debug = [];
$params_for_data_debug = [];


try {
    $nom_filtre_api = trim($_GET['nom'] ?? '');
    $specialite_filtre_api = trim($_GET['specialite'] ?? '');
    $adresse_filtre_api = trim($_GET['adresse'] ?? '');

    $current_page_api = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ["options" => ["default" => 1, "min_range" => 1]]);
    $limit_per_page_api = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ["options" => ["default" => 6, "min_range" => 1, "max_range" => 50]]);
    $offset_api = ($current_page_api - 1) * $limit_per_page_api;

    $select_clause = "SELECT id, nom, prenom, specialite, email, telephone, adresse, photo, horaires, latitude, longitude";
    $from_clause = " FROM medecins";
    $base_where_clause = " WHERE valide = 1";

    $additional_conditions_array = [];

    if (!empty($nom_filtre_api)) {
      
        $conditions_nom_parts = [];
        $nom_param_value = "%$nom_filtre_api%";
        $conditions_nom_parts[] = "LOWER(nom) LIKE LOWER(:search_nom_n)";
        $conditions_nom_parts[] = "LOWER(prenom) LIKE LOWER(:search_nom_p)";
        $conditions_nom_parts[] = "LOWER(email) LIKE LOWER(:search_nom_e)";
        $conditions_nom_parts[] = "CONCAT(LOWER(prenom), ' ', LOWER(nom)) LIKE LOWER(:search_nom_pn)";
        $conditions_nom_parts[] = "CONCAT(LOWER(nom), ' ', LOWER(prenom)) LIKE LOWER(:search_nom_np)";
        $additional_conditions_array[] = "(" . implode(" OR ", $conditions_nom_parts) . ")";
    }
    if (!empty($specialite_filtre_api)) {
        $additional_conditions_array[] = "LOWER(specialite) = LOWER(:specialite_api)";
    }
    if (!empty($adresse_filtre_api)) {
        $additional_conditions_array[] = "LOWER(adresse) LIKE LOWER(:adresse_api)";
    }

    $dynamic_where_clause_string = "";
    if (count($additional_conditions_array) > 0) {
        if (count($additional_conditions_array) == 1 && !empty($nom_filtre_api) && empty($specialite_filtre_api) && empty($adresse_filtre_api)) {
             // Cas spécial si SEULE la condition de nom est active (elle a déjà ses parenthèses)
            $dynamic_where_clause_string = " AND " . $additional_conditions_array[0];
        } elseif(count($additional_conditions_array) > 0) {
            $dynamic_where_clause_string = " AND (" . implode(" AND ", $additional_conditions_array) . ")";
        }
    }


    // Requête de COMPTAGE
    $sql_count_api_final = "SELECT COUNT(*) " . $from_clause . $base_where_clause . $dynamic_where_clause_string;
    $stmt_count_api = $pdo->prepare($sql_count_api_final);
    $params_for_count_debug = [];

    if (!empty($nom_filtre_api)) {
        $stmt_count_api->bindValue(':search_nom_n', $nom_param_value, PDO::PARAM_STR);
        $stmt_count_api->bindValue(':search_nom_p', $nom_param_value, PDO::PARAM_STR);
        $stmt_count_api->bindValue(':search_nom_e', $nom_param_value, PDO::PARAM_STR);
        $stmt_count_api->bindValue(':search_nom_pn', $nom_param_value, PDO::PARAM_STR);
        $stmt_count_api->bindValue(':search_nom_np', $nom_param_value, PDO::PARAM_STR);
        $params_for_count_debug['search_nom_all'] = $nom_param_value; // Juste pour le log
    }
    if (!empty($specialite_filtre_api)) {
        $stmt_count_api->bindValue(':specialite_api', $specialite_filtre_api, PDO::PARAM_STR);
        $params_for_count_debug[':specialite_api'] = $specialite_filtre_api;
    }
    if (!empty($adresse_filtre_api)) {
        $stmt_count_api->bindValue(':adresse_api', "%$adresse_filtre_api%", PDO::PARAM_STR);
        $params_for_count_debug[':adresse_api'] = "%$adresse_filtre_api%";
    }
    $stmt_count_api->execute();
    $total_items_api = (int)$stmt_count_api->fetchColumn();
    $total_pages_api = $total_items_api > 0 ? ceil($total_items_api / $limit_per_page_api) : 0;


    // Requête de DONNÉES
    $sql_medecins_api_final = $select_clause . $from_clause . $base_where_clause . $dynamic_where_clause_string
                      . " ORDER BY nom ASC, prenom ASC"
                      . " LIMIT :limit_api OFFSET :offset_api";

    $stmt_medecins_api = $pdo->prepare($sql_medecins_api_final);
    $params_for_data_debug = [];

    if (!empty($nom_filtre_api)) {
        $stmt_medecins_api->bindValue(':search_nom_n', $nom_param_value, PDO::PARAM_STR);
        $stmt_medecins_api->bindValue(':search_nom_p', $nom_param_value, PDO::PARAM_STR);
        $stmt_medecins_api->bindValue(':search_nom_e', $nom_param_value, PDO::PARAM_STR);
        $stmt_medecins_api->bindValue(':search_nom_pn', $nom_param_value, PDO::PARAM_STR);
        $stmt_medecins_api->bindValue(':search_nom_np', $nom_param_value, PDO::PARAM_STR);
        $params_for_data_debug['search_nom_all'] = $nom_param_value;
    }
    if (!empty($specialite_filtre_api)) {
        $stmt_medecins_api->bindValue(':specialite_api', $specialite_filtre_api, PDO::PARAM_STR);
        $params_for_data_debug[':specialite_api'] = $specialite_filtre_api;
    }
    if (!empty($adresse_filtre_api)) {
        $stmt_medecins_api->bindValue(':adresse_api', "%$adresse_filtre_api%", PDO::PARAM_STR);
        $params_for_data_debug[':adresse_api'] = "%$adresse_filtre_api%";
    }
    $stmt_medecins_api->bindValue(':limit_api', (int)$limit_per_page_api, PDO::PARAM_INT);
    $stmt_medecins_api->bindValue(':offset_api', (int)$offset_api, PDO::PARAM_INT);
    $params_for_data_debug[':limit_api'] = (int)$limit_per_page_api;
    $params_for_data_debug[':offset_api'] = (int)$offset_api;

    $stmt_medecins_api->execute();
    $medecins_result = $stmt_medecins_api->fetchAll(PDO::FETCH_ASSOC);

    $response_data_api = [
        'medecins' => $medecins_result,
        'pagination' => [ /* ... */ ],
        'filtersApplied' => [ /* ... */ ]
    ];
     $response_data_api['pagination'] = [
            'currentPage' => $current_page_api,
            'perPage' => $limit_per_page_api,
            'totalPages' => $total_pages_api,
            'totalItems' => $total_items_api
        ];
     $response_data_api['filtersApplied'] = [
            'nom' => $nom_filtre_api,
            'specialite' => $specialite_filtre_api,
            'adresse' => $adresse_filtre_api
        ];

    echo json_encode($response_data_api);

} catch (PDOException $e) {
    error_log("Erreur PDO API (api_medecins.php): " . $e->getMessage() .
              " | SQL (count): " . ($sql_count_api_final ?? 'Non défini') . " - Params (count): " . json_encode($params_for_count_debug) .
              " | SQL (data): " . ($sql_medecins_api_final ?? 'Non défini') . " - Params (data): " . json_encode($params_for_data_debug));

    http_response_code(500);
    echo json_encode([
        "error" => "Une erreur PDO est survenue lors de la récupération des données des médecins.",
        "debug_pdo_message" => $e->getMessage(),
        "debug_sql_count" => $sql_count_api_final ?? 'Non défini',
        "debug_params_count" => $params_for_count_debug,
        "debug_sql_data" => $sql_medecins_api_final ?? 'Non défini',
        "debug_params_data" => $params_for_data_debug
    ]);
} catch (Exception $e) {
    error_log("Erreur Générale API (api_medecins.php): " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "error" => "Une erreur générale est survenue.",
        "debug_exception_message" => $e->getMessage()
    ]);
}
?>