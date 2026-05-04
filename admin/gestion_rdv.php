<?php
session_start();
require '../php/db.php'; 
require_once '../php/utils/csrf_utils.php'; // Si vous avez des formulaires d'action dans la table

ini_set('display_errors', 1); 
error_reporting(E_ALL);     

if (!isset($_SESSION['admin_id'])) { 
    $_SESSION['flash_message_login'] = "Accès refusé. Veuillez vous connecter.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/admin-login.php'); 
    exit;
}

$search_rdv_admin = trim($_GET['search'] ?? '');
$status_filter_rdv_admin = $_GET['status'] ?? ''; 
$medecin_filter_rdv_admin = filter_input(INPUT_GET, 'medecin', FILTER_VALIDATE_INT);
$patient_filter_rdv_admin = filter_input(INPUT_GET, 'patient', FILTER_VALIDATE_INT);
$date_filter_rdv_admin_debut = trim($_GET['date_debut'] ?? '');
$date_filter_rdv_admin_fin = trim($_GET['date_fin'] ?? '');

$rendezvous_list_view_admin = [];
$total_rdv_for_pagination = 0;
$total_pages_for_pagination_admin = 0;
$current_page_rdv_admin_list = 1;
$medecins_filter_list_admin = [];
$patients_filter_list_admin = [];
$statuts_rdv_enum_list = ['en attente', 'confirmé', 'annulé', 'refusé', 'terminé']; 

$all_required_tables_exist = true;
if (isset($pdo)) {
    $tables_to_check_gest_rdv = ['rendez_vous', 'medecins', 'patients'];
    foreach ($tables_to_check_gest_rdv as $table_gest_rdv) {
        try {
            if ($pdo->query("SHOW TABLES LIKE '$table_gest_rdv'")->rowCount() == 0) {
                $_SESSION['flash_message'] = "Erreur critique: La table '$table_gest_rdv' est manquante.";
                $_SESSION['flash_type'] = "danger";
                $all_required_tables_exist = false;
                break;
            }
        } catch (PDOException $e) {
             error_log("Erreur vérification table '$table_gest_rdv' (gestion_rdv.php): " . $e->getMessage());
            $_SESSION['flash_message'] = "Erreur base de données: vérification table '$table_gest_rdv' impossible.";
            $_SESSION['flash_type'] = "danger";
            $all_required_tables_exist = false;
            break;
        }
    }
     if ($all_required_tables_exist && $pdo->query("SHOW TABLES LIKE 'rendez_vous'")->rowCount() > 0) {
        try {
            $result_enum = $pdo->query("SHOW COLUMNS FROM rendez_vous LIKE 'statut'")->fetch(PDO::FETCH_ASSOC);
            if ($result_enum && preg_match_all("/'([^']+)'/", $result_enum['Type'], $matches_enum)) {
                $statuts_rdv_enum_list = $matches_enum[1];
            }
        } catch(PDOException $e) { error_log("Erreur recup ENUM RDV (gestion_rdv.php): ".$e->getMessage());}
    }
} else {
    $_SESSION['flash_message'] = "Erreur: Connexion base de données non établie.";
    $_SESSION['flash_type'] = "danger";
    $all_required_tables_exist = false;
}


if ($all_required_tables_exist) {
    $sql_from_join_part_admin = " FROM rendez_vous rv
                                  JOIN medecins m ON rv.medecin_id = m.id
                                  JOIN patients p ON rv.patient_id = p.id ";
    
    $sql_where_clauses_array_rdv = [];
    $params_for_query_rdv = []; 
    
    $sql_where_clauses_array_rdv[] = "1=1";

    if (!empty($search_rdv_admin)) {
        $search_value_rdv_param = "%$search_rdv_admin%";
        $search_conditions_rdv_group = [];

        $search_conditions_rdv_group[] = "LOWER(CONCAT(p.nom, ' ', p.prenom)) LIKE LOWER(:search_rdv_patient_name)";
        $params_for_query_rdv[':search_rdv_patient_name'] = $search_value_rdv_param;
        
        $search_conditions_rdv_group[] = "LOWER(CONCAT(m.nom, ' ', m.prenom)) LIKE LOWER(:search_rdv_medecin_name)";
        $params_for_query_rdv[':search_rdv_medecin_name'] = $search_value_rdv_param;

        $search_conditions_rdv_group[] = "LOWER(p.email) LIKE LOWER(:search_rdv_patient_email)";
        $params_for_query_rdv[':search_rdv_patient_email'] = $search_value_rdv_param;

        $search_conditions_rdv_group[] = "LOWER(m.email) LIKE LOWER(:search_rdv_medecin_email)";
        $params_for_query_rdv[':search_rdv_medecin_email'] = $search_value_rdv_param;
        
        // Vérifier si les colonnes motif_rdv et motif_annulation existent
        $rv_cols_stmt_for_search = $pdo->query("DESCRIBE rendez_vous");
        $rv_cols_for_search = $rv_cols_stmt_for_search->fetchAll(PDO::FETCH_COLUMN);

        if (in_array('motif_rdv', $rv_cols_for_search)) {
            $search_conditions_rdv_group[] = "LOWER(rv.motif_rdv) LIKE LOWER(:search_rdv_motif)";
            $params_for_query_rdv[':search_rdv_motif'] = $search_value_rdv_param;
        }
        if (in_array('motif_annulation', $rv_cols_for_search)) {
            $search_conditions_rdv_group[] = "LOWER(rv.motif_annulation) LIKE LOWER(:search_rdv_motif_annul)";
            $params_for_query_rdv[':search_rdv_motif_annul'] = $search_value_rdv_param;
        }
        
        $sql_where_clauses_array_rdv[] = "(" . implode(" OR ", $search_conditions_rdv_group) . ")";
    }

    if (!empty($status_filter_rdv_admin) && in_array($status_filter_rdv_admin, $statuts_rdv_enum_list)) {
        $sql_where_clauses_array_rdv[] = "rv.statut = :status_filter";
        $params_for_query_rdv[':status_filter'] = $status_filter_rdv_admin;
    }
    if ($medecin_filter_rdv_admin) {
        $sql_where_clauses_array_rdv[] = "rv.medecin_id = :medecin_id_filter";
        $params_for_query_rdv[':medecin_id_filter'] = $medecin_filter_rdv_admin;
    }
    if ($patient_filter_rdv_admin) {
        $sql_where_clauses_array_rdv[] = "rv.patient_id = :patient_id_filter";
        $params_for_query_rdv[':patient_id_filter'] = $patient_filter_rdv_admin;
    }
    if (!empty($date_filter_rdv_admin_debut)) {
        try { new DateTime($date_filter_rdv_admin_debut); $sql_where_clauses_array_rdv[] = "rv.date_rdv >= :date_debut_filter"; $params_for_query_rdv[':date_debut_filter'] = $date_filter_rdv_admin_debut;} catch (Exception $e) {}
    }
    if (!empty($date_filter_rdv_admin_fin)) {
         try { new DateTime($date_filter_rdv_admin_fin); $sql_where_clauses_array_rdv[] = "rv.date_rdv <= :date_fin_filter"; $params_for_query_rdv[':date_fin_filter'] = $date_filter_rdv_admin_fin;} catch (Exception $e) {}
    }
    
    $sql_where_string_rdv = " WHERE " . implode(" AND ", $sql_where_clauses_array_rdv);
    
    // --- Comptage du total ---
    $count_sql_rdv_total_admin = "SELECT COUNT(rv.id) " . $sql_from_join_part_admin . $sql_where_string_rdv;
    $stmt_count_rdv_total_admin = $pdo->prepare($count_sql_rdv_total_admin);
    $stmt_count_rdv_total_admin->execute(empty($params_for_query_rdv) ? null : $params_for_query_rdv); // Ligne 77
    $total_rdv_for_pagination = (int)$stmt_count_rdv_total_admin->fetchColumn();
    
    $per_page_rdv_admin_list = 15; 
    $current_page_rdv_admin_list = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ["options" => ["min_range"=>1]]) ? (int)$_GET['page'] : 1;
    $offset_rdv_admin_list = ($current_page_rdv_admin_list - 1) * $per_page_rdv_admin_list;
    $total_pages_for_pagination_admin = $total_rdv_for_pagination > 0 ? ceil($total_rdv_for_pagination / $per_page_rdv_admin_list) : 0;

    // --- Sélection des données ---
    $sql_select_part_admin = "SELECT rv.id, rv.date_rdv, rv.heure_rdv, rv.statut, rv.motif_rdv, rv.motif_annulation, m.id AS medecin_id_val, m.nom AS medecin_nom, m.prenom AS medecin_prenom, p.id AS patient_id_val, p.nom AS patient_nom, p.prenom AS patient_prenom ";
    $sql_rdv_final_admin = $sql_select_part_admin . $sql_from_join_part_admin . $sql_where_string_rdv . " ORDER BY rv.date_rdv DESC, rv.heure_rdv DESC LIMIT :limit OFFSET :offset";
    
    $stmt_rdv_list_admin_final = $pdo->prepare($sql_rdv_final_admin);
    
    $params_for_data_paginated_rdv = $params_for_query_rdv;
    $params_for_data_paginated_rdv[':limit'] = $per_page_rdv_admin_list;
    $params_for_data_paginated_rdv[':offset'] = $offset_rdv_admin_list;
    
    $stmt_rdv_list_admin_final->execute($params_for_data_paginated_rdv);
    $rendezvous_list_view_admin = $stmt_rdv_list_admin_final->fetchAll(PDO::FETCH_ASSOC);

    if (isset($pdo)) {
        $medecins_filter_list_admin = $pdo->query("SELECT id, nom, prenom FROM medecins WHERE valide = 1 ORDER BY nom, prenom")->fetchAll(PDO::FETCH_ASSOC);
        $patients_filter_list_admin = $pdo->query("SELECT id, nom, prenom FROM patients ORDER BY nom, prenom")->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ... (Reste du fichier HTML identique)
?>
<!DOCTYPE html>
<!-- Le reste du HTML reste identique -->
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Rendez-vous - Admin SANTE TV</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="admin-gestion-page body-admin-gestion-rdv">

<header class="site-header admin-header">
    <div class="container">
        <div class="logo-branding"><a href="dashboard_admin.php" title="Tableau de Bord Admin"><img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img"><span class="site-title">Admin SANTE TV</span></a></div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav"><span></span><span></span><span></span></button>
        <nav class="main-navigation admin-navigation" id="main-nav">
             <ul>
                <li><a href="dashboard_admin.php" class="nav-link">Dashboard</a></li>
                <li><a href="gestion_rdv.php" class="nav-link active">Rendez-vous</a></li>
                <li><a href="envoyer_emails_masse.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'envoyer_emails_masse.php' || basename($_SERVER['PHP_SELF']) == 'envoyer_email_specifique.php') ? 'active' : ''; ?>"> <i class="fas fa-mail-bulk icon-left"></i>Email en Masse</a></li>
                <li><a href="gestion_commentaires.php" class="nav-link">Commentaires <?php if(isset($nb_com_att_nav_gest_rdv) && $nb_com_att_nav_gest_rdv > 0): ?><span class="badge-notification"><?= $nb_com_att_nav_gest_rdv ?></span><?php endif; ?></a></li>
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
            <h1 class="page-main-title"><i class="fas fa-calendar-alt page-icon"></i> Gestion de Tous les Rendez-vous</h1>
            <p class="section-subtitle">Consultez, filtrez et accédez aux détails de tous les rendez-vous de la plateforme.</p>
        </div>

        <?php if (isset($flash_message_gest_rdv_page) && $flash_message_gest_rdv_page): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_gest_rdv_page) ?> alert-dismissible">
                <?= htmlspecialchars($flash_message_gest_rdv_page) ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button> 
            </div>
        <?php endif; ?>
        <?php if (!$all_required_tables_exist): ?>
            <div class="alert alert-danger">Certaines tables de base de données sont manquantes. La fonctionnalité est limitée.</div>
        <?php else: ?>
            <div class="filters-toolbar">
                <form method="GET" action="gestion_rdv.php" class="filter-form-inline">
                    <div class="form-group search-group">
                        <label for="search_rdv_admin_input" class="sr-only">Rechercher</label>
                        <div class="input-with-icon">
                            <i class="fas fa-search input-icon"></i>
                            <input type="search" id="search_rdv_admin_input" name="search" placeholder="Patient, Médecin, Email, Motif..." value="<?= htmlspecialchars($search_rdv_admin) ?>" class="form-control">
                        </div>
                    </div>
                    <div class="form-group filter-group">
                        <label for="status_filter_rdv_admin_select" class="sr-only">Statut</label>
                        <select id="status_filter_rdv_admin_select" name="status" class="form-control">
                            <option value="">Tous les Statuts</option>
                            <?php foreach ($statuts_rdv_enum_list as $statut_opt): ?>
                            <option value="<?= htmlspecialchars($statut_opt) ?>" <?= ($status_filter_rdv_admin === $statut_opt) ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($statut_opt)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group filter-group">
                        <label for="medecin_filter_rdv_admin_select" class="sr-only">Médecin</label>
                        <select id="medecin_filter_rdv_admin_select" name="medecin" class="form-control">
                            <option value="">Tous les Médecins</option>
                            <?php foreach ($medecins_filter_list_admin as $med_filter): ?>
                                <option value="<?= htmlspecialchars($med_filter['id']) ?>" <?= ($medecin_filter_rdv_admin == $med_filter['id']) ? 'selected' : '' ?>> Dr. <?= htmlspecialchars($med_filter['prenom'] . ' ' . $med_filter['nom']) ?> </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group filter-group">
                        <label for="patient_filter_rdv_admin_select" class="sr-only">Patient</label>
                        <select id="patient_filter_rdv_admin_select" name="patient" class="form-control">
                            <option value="">Tous les Patients</option>
                             <?php foreach ($patients_filter_list_admin as $pat_filter): ?>
                                <option value="<?= htmlspecialchars($pat_filter['id']) ?>" <?= ($patient_filter_rdv_admin == $pat_filter['id']) ? 'selected' : '' ?>> <?= htmlspecialchars($pat_filter['prenom'] . ' ' . $pat_filter['nom']) ?> </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group filter-group">
                        <label for="date_debut_filter_rdv">Date Début:</label>
                        <input type="date" id="date_debut_filter_rdv" name="date_debut" value="<?= htmlspecialchars($date_filter_rdv_admin_debut) ?>" class="form-control">
                    </div>
                    <div class="form-group filter-group">
                        <label for="date_fin_filter_rdv">Date Fin:</label>
                        <input type="date" id="date_fin_filter_rdv" name="date_fin" value="<?= htmlspecialchars($date_filter_rdv_admin_fin) ?>" class="form-control">
                    </div>
                    <button type="submit" class="btn primary-action filter-submit-button"><i class="fas fa-filter icon-left"></i>Filtrer</button>
                    <?php if (!empty($search_rdv_admin) || !empty($status_filter_rdv_admin) || $medecin_filter_rdv_admin || $patient_filter_rdv_admin || !empty($date_filter_rdv_admin_debut) || !empty($date_filter_rdv_admin_fin)): ?>
                        <a href="gestion_rdv.php" class="btn secondary-action filter-reset-button"><i class="fas fa-undo icon-left"></i>Réinitialiser</a>
                    <?php endif; ?>
                </form>
            </div>

            <section class="rdv-management-table">
                <?php if (isset($rendezvous_list_view_admin) && count($rendezvous_list_view_admin) > 0): ?>
                    <div class="table-responsive-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID RDV</th><th>Patient</th><th>Médecin</th><th>Date</th><th>Heure</th><th>Statut</th><th class="actions-cell">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rendezvous_list_view_admin as $rdv_item_admin): ?>
                                    <tr id="rdv-row-<?= htmlspecialchars($rdv_item_admin['id']) ?>">
                                        <td data-label="ID RDV">#<?= htmlspecialchars($rdv_item_admin['id']) ?></td>
                                        <td data-label="Patient"><a href="voir_patient.php?id=<?= htmlspecialchars($rdv_item_admin['patient_id_val']) ?>" title="Voir profil patient"><?= htmlspecialchars($rdv_item_admin['patient_prenom'] . ' ' . $rdv_item_admin['patient_nom']) ?></a></td>
                                        <td data-label="Médecin"><a href="voir_medecin.php?id=<?= htmlspecialchars($rdv_item_admin['medecin_id_val']) ?>" title="Voir profil médecin">Dr. <?= htmlspecialchars($rdv_item_admin['medecin_prenom'] . ' ' . $rdv_item_admin['medecin_nom']) ?></a></td>
                                        <td data-label="Date"><?= htmlspecialchars(date('d/m/Y', strtotime($rdv_item_admin['date_rdv']))) ?></td>
                                        <td data-label="Heure"><?= htmlspecialchars(date('H:i', strtotime($rdv_item_admin['heure_rdv']))) ?></td>
                                        <td data-label="Statut">
                                            <?php 
                                            $statut_rdv_admin = strtolower($rdv_item_admin['statut']);
                                            $statut_class_rdv_admin = 'statut-' . str_replace([' ', '_'], '-', $statut_rdv_admin);
                                            ?>
                                            <span class="status-badge <?= htmlspecialchars($statut_class_rdv_admin) ?>"><?= htmlspecialchars(ucfirst($rdv_item_admin['statut'])) ?></span>
                                        </td>
                                        <td class="actions-cell">
                                            <a href="voir_rdv.php?id=<?= htmlspecialchars($rdv_item_admin['id']) ?>" class="btn btn-xs btn-info" title="Voir Détails du RDV"><i class="fas fa-eye"></i></a>
                                            <form action="supprimer_rdv.php" method="POST" style="display:inline-block; margin:0 2px;" onsubmit="return confirm('Supprimer définitivement ce rendez-vous ? Cette action est irréversible.')">
                                                <?= csrf_input_field() ?>
                                                <input type="hidden" name="id" value="<?= htmlspecialchars($rdv_item_admin['id']) ?>">
                                                <input type="hidden" name="return_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                                <button type="submit" class="btn btn-xs btn-danger" title="Supprimer ce RDV">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_pages_for_pagination_admin > 1): ?>
                        <div class="pagination-controls-wrapper">
                            <span class="pagination-summary">Page <?= $current_page_rdv_admin_list ?> sur <?= $total_pages_for_pagination_admin ?> (Total: <?= $total_rdv_for_pagination ?> RDV)</span>
                            <nav class="pagination-nav">
                                <?php 
                                $base_query_params_rdv = $_GET; 
                                unset($base_query_params_rdv['page']);

                                if ($current_page_rdv_admin_list > 1) {
                                    $first_page_params_rdv = $base_query_params_rdv; $first_page_params_rdv['page'] = 1;
                                    echo '<a href="?'.http_build_query($first_page_params_rdv).'" class="page-link" title="Première page">««</a>';
                                    $prev_page_params_rdv = $base_query_params_rdv; $prev_page_params_rdv['page'] = $current_page_rdv_admin_list - 1;
                                    echo '<a href="?'.http_build_query($prev_page_params_rdv).'" class="page-link">« Préc.</a>';
                                } else {
                                    echo '<span class="page-link disabled">««</span>';
                                    echo '<span class="page-link disabled">« Préc.</span>';
                                }
                                
                                $num_links_pagination_rdv = 2; 
                                $start_page_rdv = max(1, $current_page_rdv_admin_list - $num_links_pagination_rdv);
                                $end_page_rdv = min($total_pages_for_pagination_admin, $current_page_rdv_admin_list + $num_links_pagination_rdv);

                                if ($start_page_rdv > 1) {
                                    $page_1_params_rdv = $base_query_params_rdv; $page_1_params_rdv['page'] = 1;
                                    echo '<a href="?'.http_build_query($page_1_params_rdv).'" class="page-link">1</a>';
                                    if ($start_page_rdv > 2) { echo '<span class="ellipsis">…</span>'; }
                                }

                                for ($i_rdv_page = $start_page_rdv; $i_rdv_page <= $end_page_rdv; $i_rdv_page++): 
                                    $loop_page_params_rdv = $base_query_params_rdv; $loop_page_params_rdv['page'] = $i_rdv_page;
                                    $active_class_rdv = ($i_rdv_page == $current_page_rdv_admin_list) ? 'active' : ''; 
                                    echo '<a href="?'.http_build_query($loop_page_params_rdv).'" class="page-link '.$active_class_rdv.'">'.$i_rdv_page.'</a>';
                                endfor; 
                                
                                if ($end_page_rdv < $total_pages_for_pagination_admin) {
                                    if ($end_page_rdv < $total_pages_for_pagination_admin - 1) { echo '<span class="ellipsis">…</span>'; }
                                    $last_page_direct_params_rdv = $base_query_params_rdv; $last_page_direct_params_rdv['page'] = $total_pages_for_pagination_admin;
                                    echo '<a href="?'.http_build_query($last_page_direct_params_rdv).'" class="page-link">'.$total_pages_for_pagination_admin.'</a>';
                                }

                                if ($current_page_rdv_admin_list < $total_pages_for_pagination_admin) {
                                    $next_page_params_rdv = $base_query_params_rdv; $next_page_params_rdv['page'] = $current_page_rdv_admin_list + 1;
                                    echo '<a href="?'.http_build_query($next_page_params_rdv).'" class="page-link">Suiv. »</a>';
                                    $last_page_nav_params_rdv = $base_query_params_rdv; $last_page_nav_params_rdv['page'] = $total_pages_for_pagination_admin;
                                    echo '<a href="?'.http_build_query($last_page_nav_params_rdv).'" class="page-link" title="Dernière page">»»</a>';
                                } else {
                                     echo '<span class="page-link disabled">Suiv. »</span>';
                                     echo '<span class="page-link disabled">»»</span>';
                                }
                                ?>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                     <p class="no-messages text-center">
                        <?php 
                        if (!$all_required_tables_exist && isset($pdo)):
                            echo "Une ou plusieurs tables essentielles sont manquantes.";
                        elseif (!isset($pdo)):
                             echo "Erreur de connexion à la base de données.";
                        elseif (!empty($search_rdv_admin) || !empty($status_filter_rdv_admin) || $medecin_filter_rdv_admin || $patient_filter_rdv_admin || !empty($date_filter_rdv_admin_debut) || !empty($date_filter_rdv_admin_fin)): 
                            echo "Aucun rendez-vous ne correspond à vos critères de recherche.";
                        else: 
                            echo "Aucun rendez-vous n'a été enregistré pour le moment.";
                        endif; 
                        ?>
                    </p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>

<footer class="site-footer admin-footer">
    <div class="container"><p class="copyright-text text-center">© <span id="footer-year"></span> SANTE TV - Espace Administration.</p></div>
</footer>
<script src="../assets/js/script.js"></script>
</body>
</html>