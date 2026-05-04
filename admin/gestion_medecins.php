<?php
session_start();
require '../php/db.php'; 
require_once '../php/utils/csrf_utils.php'; 

ini_set('display_errors', 1); 
error_reporting(E_ALL);     

if (!isset($_SESSION['admin_id'])) { 
    $_SESSION['flash_message_login'] = "Accès refusé. Veuillez vous connecter.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/admin-login.php'); 
    exit;
}

$search_med = trim($_GET['search'] ?? '');
$status_filter_med = $_GET['status'] ?? ''; 
$specialite_filter_med = trim($_GET['specialite_filter'] ?? '');

$table_medecins_exists = false;
if (isset($pdo)) {
    try {
        $table_medecins_exists = $pdo->query("SHOW TABLES LIKE 'medecins'")->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erreur vérification existence table medecins: " . $e->getMessage());
        $_SESSION['flash_message'] = "Erreur de base de données critique. Impossible de vérifier la table des médecins.";
        $_SESSION['flash_type'] = "danger";
    }
} else {
    $_SESSION['flash_message'] = "Connexion à la base de données échouée (pdo non défini).";
    $_SESSION['flash_type'] = "danger";
}

$medecins_list_view = [];
$total_medecins_gest = 0;
$total_pages_med_gest = 0;
$current_page_med_gest = 1;
$specialites_distinctes_list = [];

if ($table_medecins_exists) {
    $sql_from_clause = " FROM medecins";
    $sql_where_clauses_array = ["1=1"]; 
    $params_for_query = []; 

    if (!empty($search_med)) {
        $search_value_param = "%$search_med%";
        $search_conditions_group = [];
        $search_conditions_group[] = "LOWER(nom) LIKE LOWER(:search_med_nom)"; $params_for_query[':search_med_nom'] = $search_value_param;
        $search_conditions_group[] = "LOWER(prenom) LIKE LOWER(:search_med_prenom)"; $params_for_query[':search_med_prenom'] = $search_value_param;
        $search_conditions_group[] = "LOWER(email) LIKE LOWER(:search_med_email)"; $params_for_query[':search_med_email'] = $search_value_param;
        $search_conditions_group[] = "LOWER(specialite) LIKE LOWER(:search_med_spec_text)"; $params_for_query[':search_med_spec_text'] = $search_value_param;
        $sql_where_clauses_array[] = "(" . implode(" OR ", $search_conditions_group) . ")";
    }
    if ($status_filter_med === 'attente') {
        $sql_where_clauses_array[] = "valide = 0";
    } elseif ($status_filter_med === 'valide') {
        $sql_where_clauses_array[] = "valide = 1";
    }
    if (!empty($specialite_filter_med)) {
        $sql_where_clauses_array[] = "specialite = :specialite_dropdown_med"; 
        $params_for_query[':specialite_dropdown_med'] = $specialite_filter_med;
    }

    $sql_where_string = " WHERE " . implode(" AND ", $sql_where_clauses_array);
    $count_sql_med = "SELECT COUNT(*) " . $sql_from_clause . $sql_where_string;
    $stmt_count_med = $pdo->prepare($count_sql_med);
    $stmt_count_med->execute(empty($params_for_query) ? null : $params_for_query); 
    $total_medecins_gest = (int)$stmt_count_med->fetchColumn();

    $per_page_med_gest = defined('ELEMENTS_PAR_PAGE_ADMIN_MEDECINS_DEFAULT') ? ELEMENTS_PAR_PAGE_ADMIN_MEDECINS_DEFAULT : 10;
    $current_page_med_gest = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ["options" => ["min_range"=>1]]) ? (int)$_GET['page'] : 1;
    $offset_med_gest = ($current_page_med_gest - 1) * $per_page_med_gest;
    $total_pages_med_gest = $total_medecins_gest > 0 ? ceil($total_medecins_gest / $per_page_med_gest) : 0;

    $sql_med_list_paginated = "SELECT * " . $sql_from_clause . $sql_where_string . " ORDER BY valide ASC, nom ASC, prenom ASC LIMIT :limit OFFSET :offset";
    $stmt_medecins_list_final = $pdo->prepare($sql_med_list_paginated);
    $params_for_data_paginated = $params_for_query; 
    $params_for_data_paginated[':limit'] = $per_page_med_gest; 
    $params_for_data_paginated[':offset'] = $offset_med_gest;  
    $stmt_medecins_list_final->execute($params_for_data_paginated); 
    $medecins_list_view = $stmt_medecins_list_final->fetchAll(PDO::FETCH_ASSOC);

    try {
        if (isset($pdo)) {
            $specialites_distinctes_list = $pdo->query("SELECT DISTINCT specialite FROM medecins WHERE specialite IS NOT NULL AND specialite != '' ORDER BY specialite ASC")->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (PDOException $e) {
        error_log("Erreur récupération spécialités distinctes: " . $e->getMessage());
    }
} else {
    if (!isset($_SESSION['flash_message'])) { 
        $_SESSION['flash_message'] = "La table des médecins est inaccessible ou une erreur de configuration de la base de données est survenue.";
        $_SESSION['flash_type'] = "danger";
    }
}
$flash_message_gest_med_page = $_SESSION['flash_message'] ?? null;
$flash_type_gest_med_page = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$nb_com_att_nav_gest_med = 0; $nb_med_att_nav_gest_med = 0; 
if (isset($pdo)) { 
    try {
        if ($pdo->query("SHOW TABLES LIKE 'commentaires'")->rowCount() > 0) { $nb_com_att_nav_gest_med = (int)$pdo->query("SELECT COUNT(*) FROM commentaires WHERE statut = 'en attente'")->fetchColumn(); }
        if ($table_medecins_exists) { $nb_med_att_nav_gest_med = (int)$pdo->query("SELECT COUNT(*) FROM medecins WHERE valide = 0")->fetchColumn(); }
    } catch (PDOException $e_nav) { error_log("Erreur comptage badges nav (gestion_medecins): " . $e_nav->getMessage()); }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Médecins - Admin SANTE TV</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="admin-gestion-page body-admin-gestion-medecins">

<header class="site-header admin-header">
    <div class="container">
        <div class="logo-branding"><a href="dashboard_admin.php" title="Tableau de Bord Admin"><img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img"><span class="site-title">Admin SANTE TV</span></a></div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav"><span></span><span></span><span></span></button>
        <nav class="main-navigation admin-navigation" id="main-nav">
            <ul>
                <li><a href="dashboard_admin.php" class="nav-link">Dashboard</a></li>
                <li><a href="gestion_medecins.php" class="nav-link active">Médecins <?php if($nb_med_att_nav_gest_med > 0): ?><span class="badge-notification"><?= $nb_med_att_nav_gest_med ?></span><?php endif; ?></a></li>
                <li><a href="gestion_patients.php" class="nav-link">Patients</a></li>
                <li><a href="gestion_rdv.php" class="nav-link">Rendez-vous</a></li>
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
            <h1 class="page-main-title"><i class="fas fa-user-md page-icon"></i> Gestion des Comptes Médecins</h1>
            <p class="section-subtitle">Consultez, validez, et gérez les profils des médecins inscrits.</p>
        </div>

        <?php if ($flash_message_gest_med_page): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_gest_med_page) ?> alert-dismissible">
                <?= htmlspecialchars($flash_message_gest_med_page) ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button> 
            </div>
        <?php endif; ?>

        <div class="filters-toolbar">
            <form method="GET" action="gestion_medecins.php" class="filter-form-inline">
                 <div class="form-group search-group">
                    <label for="search_med_input" class="sr-only">Rechercher</label>
                    <div class="input-with-icon">
                        <i class="fas fa-search input-icon"></i>
                        <input type="search" id="search_med_input" name="search" placeholder="Nom, email, spécialité..." value="<?= htmlspecialchars($search_med) ?>" class="form-control">
                    </div>
                </div>
                 <div class="form-group filter-group">
                    <label for="status_filter_med_select" class="sr-only">Statut</label>
                    <select id="status_filter_med_select" name="status" class="form-control">
                        <option value="">Tous les Statuts</option>
                        <option value="attente" <?= ($status_filter_med === 'attente') ? 'selected' : '' ?>>En attente</option>
                        <option value="valide" <?= ($status_filter_med === 'valide') ? 'selected' : '' ?>>Validé</option>
                    </select>
                </div>
                <div class="form-group filter-group">
                    <label for="specialite_filter_med_select" class="sr-only">Spécialité</label>
                    <select id="specialite_filter_med_select" name="specialite_filter" class="form-control">
                        <option value="">Toutes les Spécialités</option>
                        <?php foreach ($specialites_distinctes_list as $spec_item): ?>
                             <option value="<?= htmlspecialchars($spec_item) ?>" <?= (strtolower($specialite_filter_med) === strtolower($spec_item)) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($spec_item)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn primary-action filter-submit-button"><i class="fas fa-filter icon-left"></i>Filtrer</button>
                <?php if (!empty($search_med) || !empty($status_filter_med) || !empty($specialite_filter_med)): ?>
                    <a href="gestion_medecins.php" class="btn secondary-action filter-reset-button"><i class="fas fa-undo icon-left"></i>Réinitialiser</a>
                <?php endif; ?>
            </form>
        </div>

        <section class="doctors-management-table">
             <?php if (isset($medecins_list_view) && count($medecins_list_view) > 0): ?>
                <div class="table-responsive-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th><th>Photo</th><th>Nom Complet</th><th>Email</th><th>Spécialité</th><th>Téléphone</th><th>Statut</th><th class="actions-cell">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($medecins_list_view as $med_item): ?>
                            <tr id="med-row-<?= htmlspecialchars($med_item['id']) ?>">
                                <td data-label="ID"><?= htmlspecialchars($med_item['id']) ?></td>
                                <td data-label="Photo">
                                    <img src="<?= !empty($med_item['photo']) ? '../' . htmlspecialchars(ltrim($med_item['photo'], '/')) : '../assets/images/placeholder-medecin.jpg' ?>" 
                                         alt="Photo Dr. <?= htmlspecialchars($med_item['nom'] . ' ' . $med_item['prenom']) ?>" 
                                         class="table-avatar-img"
                                         onerror="this.onerror=null;this.src='../assets/images/placeholder-medecin.jpg';">
                                </td>
                                <td data-label="Nom Complet"><?= htmlspecialchars($med_item['prenom'] . ' ' . $med_item['nom']) ?></td>
                                <td data-label="Email"><a href="mailto:<?= htmlspecialchars($med_item['email']) ?>"><?= htmlspecialchars($med_item['email']) ?></a></td>
                                <td data-label="Spécialité"><?= htmlspecialchars(ucfirst($med_item['specialite'] ?? 'N/A')) ?></td>
                                <td data-label="Téléphone"><?= htmlspecialchars($med_item['telephone'] ?? 'N/A') ?></td>
                                <td data-label="Statut">
                                    <?php if (isset($med_item['valide']) && $med_item['valide'] == 1): ?>
                                        <span class="status-badge status-valide">Validé</span>
                                    <?php elseif (isset($med_item['valide']) && $med_item['valide'] == 0): ?>
                                        <span class="status-badge status-attente">En attente</span>
                                    <?php else: ?>
                                        <span class="status-badge status-autre">Inconnu</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <a href="voir_medecin.php?id=<?= htmlspecialchars($med_item['id']) ?>" class="btn btn-xs btn-info" title="Voir Détails"><i class="fas fa-eye"></i></a>
                                    <?php if (isset($med_item['valide']) && $med_item['valide'] == 0): ?>
                                        <form action="valider_medecin.php" method="POST" style="display:inline-block; margin:0 2px;">
                                            <?= csrf_input_field() ?>
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($med_item['id']) ?>">
                                            <input type="hidden" name="return_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                            <button type="submit" class="btn btn-xs btn-success" title="Valider ce médecin"><i class="fas fa-check"></i></button>
                                        </form>
                                        <form action="refuser_medecin.php" method="POST" style="display:inline-block; margin:0 2px;" onsubmit="return confirm('Refuser et supprimer cette demande d\'inscription ?\nATTENTION: Les fichiers associés seront aussi supprimés.')">
                                            <?= csrf_input_field() ?>
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($med_item['id']) ?>">
                                            <input type="hidden" name="return_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                            <button type="submit" class="btn btn-xs btn-danger" title="Refuser et Supprimer"><i class="fas fa-user-times"></i></button>
                                        </form>
                                    <?php elseif (isset($med_item['valide']) && $med_item['valide'] == 1): ?>
                                        <form action="invalider_medecin.php" method="POST" style="display:inline-block; margin:0 2px;" onsubmit="return confirm('Rendre ce médecin inactif (non validé) ?\nIl ne pourra plus recevoir de nouveaux rendez-vous et ne sera plus listé publiquement.')">
                                            <?= csrf_input_field() ?>
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($med_item['id']) ?>">
                                            <input type="hidden" name="return_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                            <button type="submit" class="btn btn-xs btn-warning" title="Rendre Inactif"><i class="fas fa-user-slash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <!-- Bouton Supprimer (pour tous les médecins, validés ou non) -->
                                    <form action="supprimer_medecin.php" method="POST" style="display:inline-block; margin:0 2px;" onsubmit="return confirm('SUPPRIMER DÉFINITIVEMENT ce compte médecin et toutes ses données associées (RDV, disponibilités, etc.) ?\nCette action est IRRÉVERSIBLE.')">
                                        <?= csrf_input_field() ?>
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($med_item['id']) ?>">
                                        <input type="hidden" name="return_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                        <button type="submit" class="btn btn-xs btn-danger" title="Supprimer Définitivement"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages_med_gest > 1): ?>
                <div class="pagination-controls-wrapper">
                    <span class="pagination-summary">Page <?= $current_page_med_gest ?> sur <?= $total_pages_med_gest ?> (Total: <?= $total_medecins_gest ?> médecins)</span>
                    <nav class="pagination-nav">
                        <?php 
                        $base_query_params = $_GET; 
                        unset($base_query_params['page']); 
                        if ($current_page_med_gest > 1) {
                            $first_page_params = $base_query_params; $first_page_params['page'] = 1;
                            echo '<a href="?'.http_build_query($first_page_params).'" class="page-link" title="Première page">««</a>';
                            $prev_page_params = $base_query_params; $prev_page_params['page'] = $current_page_med_gest - 1;
                            echo '<a href="?'.http_build_query($prev_page_params).'" class="page-link">« Préc.</a>';
                        } else {
                            echo '<span class="page-link disabled">««</span>';
                            echo '<span class="page-link disabled">« Préc.</span>';
                        }
                        $num_links_pagination = 2; 
                        $start_page = max(1, $current_page_med_gest - $num_links_pagination);
                        $end_page = min($total_pages_med_gest, $current_page_med_gest + $num_links_pagination);
                        if ($start_page > 1) {
                            $page_1_params = $base_query_params; $page_1_params['page'] = 1;
                            echo '<a href="?'.http_build_query($page_1_params).'" class="page-link">1</a>';
                            if ($start_page > 2) { echo '<span class="ellipsis">…</span>'; }
                        }
                        for ($i_med_page = $start_page; $i_med_page <= $end_page; $i_med_page++): 
                            $loop_page_params = $base_query_params; $loop_page_params['page'] = $i_med_page;
                            $active_class_med = ($i_med_page == $current_page_med_gest) ? 'active' : ''; 
                            echo '<a href="?'.http_build_query($loop_page_params).'" class="page-link '.$active_class_med.'">'.$i_med_page.'</a>';
                        endfor; 
                        if ($end_page < $total_pages_med_gest) {
                            if ($end_page < $total_pages_med_gest - 1) { echo '<span class="ellipsis">…</span>'; }
                            $last_page_direct_params = $base_query_params; $last_page_direct_params['page'] = $total_pages_med_gest;
                            echo '<a href="?'.http_build_query($last_page_direct_params).'" class="page-link">'.$total_pages_med_gest.'</a>';
                        }
                        if ($current_page_med_gest < $total_pages_med_gest) {
                            $next_page_params = $base_query_params; $next_page_params['page'] = $current_page_med_gest + 1;
                            echo '<a href="?'.http_build_query($next_page_params).'" class="page-link">Suiv. »</a>';
                            $last_page_nav_params = $base_query_params; $last_page_nav_params['page'] = $total_pages_med_gest;
                            echo '<a href="?'.http_build_query($last_page_nav_params).'" class="page-link" title="Dernière page">»»</a>';
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
                    if (!$table_medecins_exists && isset($pdo)):
                        echo "La table des médecins semble manquante.";
                    elseif (!isset($pdo)):
                        echo "Erreur de connexion à la base de données. Impossible d'accéder aux données.";
                    elseif (!empty($search_med) || !empty($status_filter_med) || !empty($specialite_filter_med)): 
                        echo "Aucun médecin ne correspond à vos critères de recherche.";
                    else: 
                        echo "Aucun médecin n'est encore inscrit.";
                    endif; 
                    ?>
                </p>
            <?php endif; ?>
        </section>
    </div>
</main>
<footer class="site-footer admin-footer"><div class="container"><p class="copyright-text text-center">© <span id="footer-year"></span> SANTE TV - Espace Administration.</p></div></footer>
<script src="../assets/js/script.js"></script>
</body>
</html>