<?php
session_start();
require '../php/db.php'; 
require_once '../php/utils/csrf_utils.php'; // Au cas où pour les formulaires d'action futurs

ini_set('display_errors', 1); 
error_reporting(E_ALL);     

if (!isset($_SESSION['admin_id'])) { 
    $_SESSION['flash_message_login'] = "Accès refusé. Veuillez vous connecter.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/admin-login.php'); 
    exit;
}

$search_patient_admin = trim($_GET['search'] ?? '');
$table_patients_exists = false;
if(isset($pdo)) {
    try {
        $table_patients_exists = $pdo->query("SHOW TABLES LIKE 'patients'")->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erreur vérification table patients (gestion_patients.php): " . $e->getMessage());
        $_SESSION['flash_message'] = "Erreur base de données: vérification table patients impossible.";
        $_SESSION['flash_type'] = "danger";
    }
} else {
     $_SESSION['flash_message'] = "Erreur: Connexion base de données non établie.";
     $_SESSION['flash_type'] = "danger";
}

$patients_list_view_admin = [];
$total_patients_admin_list = 0;
$total_pages_patients_admin = 0;
$current_page_patients_admin = 1;

if ($table_patients_exists) {
    $sql_base_from_patients = " FROM patients";
    $sql_where_clauses_array_patients = [];
    $params_for_query_patients = []; 
    
    $sql_where_clauses_array_patients[] = "1=1";

    if (!empty($search_patient_admin)) {
        $search_value_patient_param = "%$search_patient_admin%";
        $search_conditions_patient_group = [];

        $search_conditions_patient_group[] = "LOWER(nom) LIKE LOWER(:search_pat_nom_field)";
        $params_for_query_patients[':search_pat_nom_field'] = $search_value_patient_param;

        $search_conditions_patient_group[] = "LOWER(prenom) LIKE LOWER(:search_pat_prenom_field)";
        $params_for_query_patients[':search_pat_prenom_field'] = $search_value_patient_param;

        $search_conditions_patient_group[] = "LOWER(email) LIKE LOWER(:search_pat_email_field)";
        $params_for_query_patients[':search_pat_email_field'] = $search_value_patient_param;
        
        $patient_cols_check_stmt = $pdo->query("DESCRIBE patients");
        $patient_columns = $patient_cols_check_stmt->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('adresse', $patient_columns)) {
            $search_conditions_patient_group[] = "LOWER(adresse) LIKE LOWER(:search_pat_adresse_field)";
            $params_for_query_patients[':search_pat_adresse_field'] = $search_value_patient_param;
        }

        $search_conditions_patient_group[] = "LOWER(CONCAT(prenom, ' ', nom)) LIKE LOWER(:search_pat_concat_pn_field)";
        $params_for_query_patients[':search_pat_concat_pn_field'] = $search_value_patient_param;

        $search_conditions_patient_group[] = "LOWER(CONCAT(nom, ' ', prenom)) LIKE LOWER(:search_pat_concat_np_field)";
        $params_for_query_patients[':search_pat_concat_np_field'] = $search_value_patient_param;
        
        $sql_where_clauses_array_patients[] = "(" . implode(" OR ", $search_conditions_patient_group) . ")";
    }
    
    $sql_where_string_patients = " WHERE " . implode(" AND ", $sql_where_clauses_array_patients);
    
    // --- Comptage du total ---
    $count_sql_patients_admin = "SELECT COUNT(*) " . $sql_base_from_patients . $sql_where_string_patients;
    $stmt_count_patients_admin = $pdo->prepare($count_sql_patients_admin);
    $stmt_count_patients_admin->execute(empty($params_for_query_patients) ? null : $params_for_query_patients);
    $total_patients_admin_list = (int)$stmt_count_patients_admin->fetchColumn();

    $per_page_patients_admin = 15; 
    $current_page_patients_admin = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ["options" => ["min_range"=>1]]) ? (int)$_GET['page'] : 1;
    $offset_patients_admin = ($current_page_patients_admin - 1) * $per_page_patients_admin;
    $total_pages_patients_admin = $total_patients_admin_list > 0 ? ceil($total_patients_admin_list / $per_page_patients_admin) : 0;

    // --- Sélection des données ---
    $sql_patients_list_paginated = "SELECT * " . $sql_base_from_patients . $sql_where_string_patients . " ORDER BY nom ASC, prenom ASC LIMIT :limit OFFSET :offset";
    
    $stmt_patients_list_final_admin = $pdo->prepare($sql_patients_list_paginated);
    
    $params_for_data_paginated_patients = $params_for_query_patients;
    $params_for_data_paginated_patients[':limit'] = $per_page_patients_admin;
    $params_for_data_paginated_patients[':offset'] = $offset_patients_admin;
    
    $stmt_patients_list_final_admin->execute($params_for_data_paginated_patients);
    $patients_list_view_admin = $stmt_patients_list_final_admin->fetchAll(PDO::FETCH_ASSOC);

} else {
    if (!isset($_SESSION['flash_message'])) { 
        $_SESSION['flash_message'] = "La table des patients semble manquante ou une erreur de configuration est survenue.";
        $_SESSION['flash_type'] = "danger";
    }
}
$flash_message_gest_pat_page = $_SESSION['flash_message'] ?? null;
$flash_type_gest_pat_page = $_SESSION['flash_type'] ?? '';
if (empty($e_main_error_occurred_pat)) { 
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

$nb_com_att_nav_gest_pat = 0;
$nb_med_att_nav_gest_pat = 0;
if (isset($pdo)) {
    if ($pdo->query("SHOW TABLES LIKE 'commentaires'")->rowCount() > 0) {
        try { $nb_com_att_nav_gest_pat = (int)$pdo->query("SELECT COUNT(*) FROM commentaires WHERE statut = 'en attente'")->fetchColumn(); }
        catch (PDOException $e) { error_log("Erreur comptage commentaires (gest_pat): " . $e->getMessage()); }
    }
    if ($pdo->query("SHOW TABLES LIKE 'medecins'")->rowCount() > 0) {
        try { $nb_med_att_nav_gest_pat = (int)$pdo->query("SELECT COUNT(*) FROM medecins WHERE valide = 0")->fetchColumn(); }
        catch (PDOException $e) { error_log("Erreur comptage médecins (gest_pat): " . $e->getMessage()); }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Patients - Admin SANTE TV</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="admin-gestion-page body-admin-gestion-patients"> 

<header class="site-header admin-header">
    <div class="container">
        <div class="logo-branding"><a href="dashboard_admin.php" title="Tableau de Bord Admin"><img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img"><span class="site-title">Admin SANTE TV</span></a></div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav"><span></span><span></span><span></span></button>
        <nav class="main-navigation admin-navigation" id="main-nav">
             <ul>
                <li><a href="dashboard_admin.php" class="nav-link">Dashboard</a></li>
                <li><a href="gestion_medecins.php" class="nav-link">Médecins <?php if($nb_med_att_nav_gest_pat > 0): ?><span class="badge-notification"><?= $nb_med_att_nav_gest_pat ?></span><?php endif; ?></a></li>
                <li><a href="gestion_patients.php" class="nav-link active">Patients</a></li>
                <li><a href="gestion_commentaires.php" class="nav-link">Commentaires <?php if($nb_com_att_nav_gest_pat > 0): ?><span class="badge-notification"><?= $nb_com_att_nav_gest_pat ?></span><?php endif; ?></a></li>
                 <li><a href="parametres_app.php" class="nav-link">Paramètres</a></li>
                <li><a href="historique_app.php" class="nav-link">Historique</a></li>
                <li><a href="deconnexion_admin.php" class="nav-link btn btn-sm btn-danger">Déconnexion</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content admin-page-container section-padding"> 
    <div class="container">
        <div class="page-header">
            <h1 class="page-main-title"><i class="fas fa-users page-icon"></i> Gestion des Comptes Patients</h1>
            <p class="section-subtitle">Consultez et gérez la liste des patients inscrits sur la plateforme.</p>
        </div>

        <?php if ($flash_message_gest_pat_page): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_gest_pat_page) ?> alert-dismissible">
                <?= htmlspecialchars($flash_message_gest_pat_page) ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button> 
            </div>
        <?php endif; ?>

        <div class="filters-toolbar">
            <form method="GET" action="gestion_patients.php" class="filter-form-inline">
                 <div class="form-group search-group" style="flex-grow: 3;">
                    <label for="search_patient_admin_input" class="sr-only">Rechercher</label>
                     <div class="input-with-icon">
                        <i class="fas fa-search input-icon"></i>
                        <input type="search" id="search_patient_admin_input" name="search" placeholder="Rechercher par nom, prénom, email, adresse..." value="<?= htmlspecialchars($search_patient_admin) ?>" class="form-control">
                    </div>
                </div>
                <button type="submit" class="btn primary-action filter-submit-button"><i class="fas fa-search icon-left"></i>Rechercher</button>
                <?php if (!empty($search_patient_admin)): ?>
                    <a href="gestion_patients.php" class="btn secondary-action filter-reset-button"><i class="fas fa-undo icon-left"></i>Réinitialiser</a>
                <?php endif; ?>
            </form>
        </div>

        <section class="patients-management-table">
             <?php if (count($patients_list_view_admin) > 0): ?>
                <div class="table-responsive-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Photo</th>
                                <th>Nom Complet</th>
                                <th>Email</th>
                                <th>Adresse</th>
                                <th>Date Naissance</th>
                                <th>Sexe</th>
                                <th class="actions-cell">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($patients_list_view_admin as $pat_item_admin): ?>
                            <tr id="patient-row-<?= $pat_item_admin['id'] ?>">
                                <td data-label="ID"><?= $pat_item_admin['id'] ?></td>
                                <td data-label="Photo">
                                    <img src="<?= $pat_item_admin['photo'] ? '../' . htmlspecialchars($pat_item_admin['photo']) : '../assets/images/placeholder-patient.png' ?>" 
                                         alt="Photo <?= htmlspecialchars($pat_item_admin['prenom']) ?>" class="table-avatar-img">
                                </td>
                                <td data-label="Nom Complet"><?= htmlspecialchars($pat_item_admin['prenom'] . ' ' . $pat_item_admin['nom']) ?></td>
                                <td data-label="Email"><a href="mailto:<?= htmlspecialchars($pat_item_admin['email']) ?>"><?= htmlspecialchars($pat_item_admin['email']) ?></a></td>
                                <td data-label="Adresse"><?= htmlspecialchars($pat_item_admin['adresse'] ?? 'N/A') ?></td>
                                <td data-label="Date Naissance"><?= $pat_item_admin['date_naissance'] ? date('d/m/Y', strtotime($pat_item_admin['date_naissance'])) : 'N/A' ?></td>
                                <td data-label="Sexe"><?= htmlspecialchars($pat_item_admin['sexe'] ?? 'N/A') ?></td>
                                <td class="actions-cell">
                                    <a href="voir_patient.php?id=<?= $pat_item_admin['id'] ?>" class="btn btn-xs btn-info" title="Voir Détails Patient"><i class="fas fa-eye"></i></a>
                                    <a href="supprimer_patient.php?id=<?= $pat_item_admin['id'] ?>&return_url=<?= urlencode('gestion_patients.php?page='.$current_page_patients_admin.'&search='.$search_patient_admin) ?>" 
                                       class="btn btn-xs btn-danger" 
                                       onclick="return confirm('ATTENTION : La suppression d\'un patient entraîne la suppression de tous ses rendez-vous et messages associés. Êtes-vous sûr de vouloir continuer ?')" 
                                       title="Supprimer ce patient et ses données associées">
                                       <i class="fas fa-user-times"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages_patients_admin > 1): ?>
                <div class="pagination-controls-wrapper">
                    <span>Page <?= $current_page_patients_admin ?> sur <?= $total_pages_patients_admin ?> (Total: <?= $total_patients_admin_list ?> patients)</span>
                    <nav class="pagination-nav">
                        <?php 
                        $query_params_pat_admin_page = $_GET; 
                        if ($current_page_patients_admin > 1): 
                            $query_params_pat_admin_page['page'] = $current_page_patients_admin - 1; ?>
                            <a href="?<?= http_build_query($query_params_pat_admin_page) ?>" class="page-link">« Préc.</a>
                        <?php else: ?>
                            <span class="page-link disabled">« Préc.</span>
                        <?php endif; 
                        
                        $num_links_pagination = 2; 
                        $start_page = max(1, $current_page_patients_admin - $num_links_pagination);
                        $end_page = min($total_pages_patients_admin, $current_page_patients_admin + $num_links_pagination);

                        if ($start_page > 1) { $query_params_pat_admin_page['page'] = 1; echo '<a href="?'.http_build_query($query_params_pat_admin_page).'" class="page-link">1</a>'; if ($start_page > 2) { echo '<span class="ellipsis">…</span>'; } }
                        for ($i_pat_page = $start_page; $i_pat_page <= $end_page; $i_pat_page++): 
                            $query_params_pat_admin_page['page'] = $i_pat_page; 
                            $active_class_pat_admin = ($i_pat_page == $current_page_patients_admin) ? 'active' : ''; ?>
                            <a href="?<?= http_build_query($query_params_pat_admin_page) ?>" class="page-link <?= $active_class_pat_admin ?>"><?= $i_pat_page ?></a>
                        <?php endfor; 
                        if ($end_page < $total_pages_patients_admin) { if ($end_page < $total_pages_patients_admin - 1) { echo '<span class="ellipsis">…</span>'; } $query_params_pat_admin_page['page'] = $total_pages_patients_admin; echo '<a href="?'.http_build_query($query_params_pat_admin_page).'" class="page-link">'.$total_pages_patients_admin.'</a>'; }
                        
                        if ($current_page_patients_admin < $total_pages_patients_admin): 
                            $query_params_pat_admin_page['page'] = $current_page_patients_admin + 1; ?>
                            <a href="?<?= http_build_query($query_params_pat_admin_page) ?>" class="page-link">Suiv. »</a>
                        <?php else: ?>
                             <span class="page-link disabled">Suiv. »</span>
                        <?php endif; ?>
                    </nav>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <p class="no-messages text-center">
                    <?php if (!$table_patients_exists && isset($pdo)): ?>
                        La table des patients semble manquante.
                    <?php elseif (!isset($pdo)): ?>
                         Erreur de connexion à la base de données.
                    <?php elseif (!empty($search_patient_admin)): ?>
                        Aucun patient ne correspond à vos critères de recherche.
                    <?php else: ?>
                        Aucun patient n'est encore inscrit.
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </section>
        
    </div>
</main>

<footer class="site-footer admin-footer">
   <div class="container"><p class="copyright-text text-center">© <span id="footer-year"><?= date('Y') ?></span> SANTE TV - Espace Administration.</p></div>
</footer>

<script src="../assets/js/script.js"></script> 
</body>
</html>