<?php
session_start();
require '../php/db.php'; 
require_once '../php/utils/csrf_utils.php'; 

if (!isset($_SESSION['admin_id'])) { 
    $_SESSION['flash_message_login'] = "Accès refusé. Veuillez vous connecter.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/admin-login.php'); 
    exit;
}

// Pagination
$current_page_historique = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ["options" => ["min_range"=>1]]) ? (int)$_GET['page'] : 1;
$per_page_historique = 20; // Nombre d'entrées par page
$offset_historique = ($current_page_historique - 1) * $per_page_historique;

// Filtres
$filtre_type_action = trim($_GET['type_action_filtre'] ?? '');
$filtre_id_utilisateur = filter_input(INPUT_GET, 'id_utilisateur_filtre', FILTER_VALIDATE_INT);
$filtre_date_debut = trim($_GET['date_debut_filtre'] ?? '');
$filtre_date_fin = trim($_GET['date_fin_filtre'] ?? '');

$historique_list = [];
$total_historique_entries = 0;
$total_pages_historique = 0;
$types_actions_distincts = [];
$admins_distincts = [];

$table_historique_exists = $pdo->query("SHOW TABLES LIKE 'historique_actions'")->rowCount() > 0;

if (!$table_historique_exists) {
    $_SESSION['flash_message'] = "Erreur critique : La table d'historique des actions est manquante.";
    $_SESSION['flash_type'] = "danger";
} else {
    try {
        // Récupérer les types d'actions distincts pour le filtre
        $stmt_types = $pdo->query("SELECT DISTINCT type_action FROM historique_actions ORDER BY type_action ASC");
        $types_actions_distincts = $stmt_types->fetchAll(PDO::FETCH_COLUMN);

        // Récupérer les admins distincts pour le filtre (ceux qui ont loggé une action)
        $stmt_admins = $pdo->query(
            "SELECT DISTINCT ha.id_utilisateur_action, a.nom 
             FROM historique_actions ha 
             JOIN admins a ON ha.id_utilisateur_action = a.id 
             WHERE ha.type_utilisateur_action = 'admin' AND ha.id_utilisateur_action IS NOT NULL
             ORDER BY a.nom ASC"
        );
        $admins_distincts = $stmt_admins->fetchAll(PDO::FETCH_ASSOC);

        $sql_base_historique = "SELECT ha.*, a.nom as admin_nom 
                                FROM historique_actions ha 
                                LEFT JOIN admins a ON ha.id_utilisateur_action = a.id AND ha.type_utilisateur_action = 'admin'
                                WHERE 1=1";
        $params_historique_query = [];

        if (!empty($filtre_type_action)) {
            $sql_base_historique .= " AND ha.type_action = :type_action";
            $params_historique_query[':type_action'] = $filtre_type_action;
        }
        if ($filtre_id_utilisateur) {
            $sql_base_historique .= " AND ha.id_utilisateur_action = :id_utilisateur";
            $params_historique_query[':id_utilisateur'] = $filtre_id_utilisateur;
        }
        if (!empty($filtre_date_debut)) {
            $sql_base_historique .= " AND DATE(ha.date_action) >= :date_debut";
            $params_historique_query[':date_debut'] = $filtre_date_debut;
        }
        if (!empty($filtre_date_fin)) {
            $sql_base_historique .= " AND DATE(ha.date_action) <= :date_fin";
            $params_historique_query[':date_fin'] = $filtre_date_fin;
        }

        // Comptage du total pour la pagination
        $count_sql_historique = "SELECT COUNT(ha.id) 
                                 FROM historique_actions ha 
                                 LEFT JOIN admins a ON ha.id_utilisateur_action = a.id AND ha.type_utilisateur_action = 'admin'
                                 WHERE 1=1";
        if (!empty($filtre_type_action)) $count_sql_historique .= " AND ha.type_action = :type_action_count";
        if ($filtre_id_utilisateur) $count_sql_historique .= " AND ha.id_utilisateur_action = :id_utilisateur_count";
        if (!empty($filtre_date_debut)) $count_sql_historique .= " AND DATE(ha.date_action) >= :date_debut_count";
        if (!empty($filtre_date_fin)) $count_sql_historique .= " AND DATE(ha.date_action) <= :date_fin_count";
        
        $stmt_count_historique = $pdo->prepare($count_sql_historique);
        $count_params_for_execute_hist = [];
        if (!empty($filtre_type_action)) $count_params_for_execute_hist[':type_action_count'] = $filtre_type_action;
        if ($filtre_id_utilisateur) $count_params_for_execute_hist[':id_utilisateur_count'] = $filtre_id_utilisateur;
        if (!empty($filtre_date_debut)) $count_params_for_execute_hist[':date_debut_count'] = $filtre_date_debut;
        if (!empty($filtre_date_fin)) $count_params_for_execute_hist[':date_fin_count'] = $filtre_date_fin;

        $stmt_count_historique->execute($count_params_for_execute_hist);
        $total_historique_entries = (int)$stmt_count_historique->fetchColumn();
        $total_pages_historique = ceil($total_historique_entries / $per_page_historique);

        // Requête pour les données de la page actuelle
        $sql_historique_paginated = $sql_base_historique . " ORDER BY ha.date_action DESC LIMIT :limit OFFSET :offset";
        
        $params_historique_query_final = $params_historique_query;
        $params_historique_query_final[':limit'] = $per_page_historique;
        $params_historique_query_final[':offset'] = $offset_historique;
        
        $stmt_historique = $pdo->prepare($sql_historique_paginated);
        foreach($params_historique_query_final as $key => &$val){
            if($key === ':limit' || $key === ':offset' || $key === ':id_utilisateur'){
                $stmt_historique->bindValue($key, (int)$val, PDO::PARAM_INT);
            } else {
                $stmt_historique->bindValue($key, $val);
            }
        }
        unset($val);
        $stmt_historique->execute();
        $historique_list = $stmt_historique->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Erreur récupération historique: " . $e->getMessage());
        $_SESSION['flash_message'] = "Erreur lors de la récupération de l'historique.";
        $_SESSION['flash_type'] = "error";
    }
}


// Badges de navigation
$nb_med_att_nav_hist = 0; $nb_com_att_nav_hist = 0;
if ($pdo->query("SHOW TABLES LIKE 'medecins'")->rowCount() > 0) {
    $nb_med_att_nav_hist = $pdo->query("SELECT COUNT(*) FROM medecins WHERE valide = 0")->fetchColumn();
}
if ($pdo->query("SHOW TABLES LIKE 'commentaires'")->rowCount() > 0) {
    $nb_com_att_nav_hist = $pdo->query("SELECT COUNT(*) FROM commentaires WHERE statut = 'en attente'")->fetchColumn();
}

$flash_message_hist_page = $_SESSION['flash_message'] ?? null;
$flash_type_hist_page = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Actions - Admin SANTE TV</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="admin-gestion-page body-admin-historique">

<header class="site-header admin-header">
    <div class="container">
        <div class="logo-branding"><a href="dashboard_admin.php" title="Tableau de Bord Admin"><img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img"><span class="site-title">Admin SANTE TV</span></a></div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav"><span></span><span></span><span></span></button>
        <nav class="main-navigation admin-navigation" id="main-nav">
             <ul>
                <li><a href="dashboard_admin.php" class="nav-link">Dashboard</a></li>
                <li><a href="gestion_medecins.php" class="nav-link">Médecins <?php if($nb_med_att_nav_hist > 0): ?><span class="badge-notification"><?= $nb_med_att_nav_hist ?></span><?php endif; ?></a></li>
                <li><a href="gestion_patients.php" class="nav-link">Patients</a></li>
                <li><a href="envoyer_emails_masse.php" class="nav-link">Email en Masse</a></li>
                <li><a href="parametres_app.php" class="nav-link">Paramètres</a></li>
                <li><a href="historique_app.php" class="nav-link active">Historique</a></li>
                <li><a href="deconnexion_admin.php" class="nav-link btn btn-sm btn-danger">Déconnexion</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content admin-page-container section-padding"> 
    <div class="container">
        <div class="page-header">
            <h1 class="page-main-title"><i class="fas fa-history page-icon"></i> Historique des Actions de l'Application</h1>
            <p class="section-subtitle">Suivi des actions importantes réalisées sur la plateforme.</p>
        </div>

        <?php if ($flash_message_hist_page): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_hist_page) ?> alert-dismissible">
                <?= $flash_message_hist_page ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button> 
            </div>
        <?php endif; ?>

        <?php if (!$table_historique_exists): ?>
             <p class="info-message error-message"><i class="fas fa-exclamation-triangle icon-left"></i>Impossible d'afficher l'historique. La table de journalisation est manquante.</p>
        <?php else: ?>
            <div class="filters-toolbar">
                <form method="GET" action="historique_app.php" class="filter-form-inline">
                    <div class="form-group filter-group">
                        <label for="type_action_filtre" class="sr-only">Type d'action</label>
                        <select id="type_action_filtre" name="type_action_filtre" class="form-control">
                            <option value="">Tous les types d'actions</option>
                            <?php foreach ($types_actions_distincts as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>" <?= ($filtre_type_action === $type) ? 'selected' : '' ?>><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $type)))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group filter-group">
                        <label for="id_utilisateur_filtre" class="sr-only">Administrateur</label>
                        <select id="id_utilisateur_filtre" name="id_utilisateur_filtre" class="form-control">
                            <option value="">Tous les administrateurs</option>
                            <?php foreach ($admins_distincts as $admin): ?>
                                <option value="<?= $admin['id_utilisateur_action'] ?>" <?= ($filtre_id_utilisateur == $admin['id_utilisateur_action']) ? 'selected' : '' ?>><?= htmlspecialchars($admin['nom']) ?></option>
                            <?php endforeach; ?>
                            <option value="0" <?= ($filtre_id_utilisateur === '0' || ($filtre_id_utilisateur !== null && (int)$filtre_id_utilisateur === 0)) ? 'selected' : '' ?>>Système</option>
                        </select>
                    </div>
                    <div class="form-group filter-group">
                        <label for="date_debut_filtre">Date début :</label>
                        <input type="date" id="date_debut_filtre" name="date_debut_filtre" class="form-control" value="<?= htmlspecialchars($filtre_date_debut) ?>">
                    </div>
                    <div class="form-group filter-group">
                        <label for="date_fin_filtre">Date fin :</label>
                        <input type="date" id="date_fin_filtre" name="date_fin_filtre" class="form-control" value="<?= htmlspecialchars($filtre_date_fin) ?>">
                    </div>
                    <button type="submit" class="btn primary-action filter-submit-button"><i class="fas fa-filter icon-left"></i>Filtrer</button>
                     <?php if (!empty($filtre_type_action) || !empty($filtre_id_utilisateur) || !empty($filtre_date_debut) || !empty($filtre_date_fin)): ?>
                        <a href="historique_app.php" class="btn secondary-action filter-reset-button"><i class="fas fa-undo icon-left"></i>Réinitialiser</a>
                    <?php endif; ?>
                </form>
            </div>

            <section class="historique-table-section">
                <?php if (count($historique_list) > 0): ?>
                    <div class="table-responsive-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date/Heure</th>
                                    <th>Type Action</th>
                                    <th>Description</th>
                                    <th>Admin/Système</th>
                                    <th>Élément Affecté</th>
                                    <th>Détails</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($historique_list as $entree): ?>
                                <tr>
                                    <td data-label="Date/Heure"><?= date('d/m/Y H:i:s', strtotime($entree['date_action'])) ?></td>
                                    <td data-label="Type Action"><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $entree['type_action'])))) ?></td>
                                    <td data-label="Description" style="max-width:400px; white-space:normal;"><?= nl2br(htmlspecialchars($entree['description_action'])) ?></td>
                                    <td data-label="Admin/Système">
                                        <?= $entree['type_utilisateur_action'] === 'admin' && !empty($entree['admin_nom']) ? htmlspecialchars($entree['admin_nom']) . ' (ID: ' . $entree['id_utilisateur_action'] . ')' : ($entree['type_utilisateur_action'] === 'systeme' ? 'Système' : 'N/A') ?>
                                    </td>
                                    <td data-label="Élément Affecté">
                                        <?php if ($entree['id_element_concerne']): ?>
                                            <?= htmlspecialchars(ucfirst($entree['type_element_concerne'] ?? 'Élément')) ?> ID: <?= $entree['id_element_concerne'] ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Détails">
                                        <?php 
                                        if (!empty($entree['details_supplementaires'])) {
                                            $details_array = json_decode($entree['details_supplementaires'], true);
                                            if (json_last_error() === JSON_ERROR_NONE && is_array($details_array)) {
                                                echo '<ul class="details-list-hist">';
                                                foreach ($details_array as $key => $value) {
                                                    echo '<li><strong>' . htmlspecialchars(ucfirst(str_replace('_', ' ', $key))) . ':</strong> ' . htmlspecialchars(is_array($value) ? json_encode($value) : $value) . '</li>';
                                                }
                                                echo '</ul>';
                                            } else {
                                                echo nl2br(htmlspecialchars($entree['details_supplementaires']));
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages_historique > 1): ?>
                    <div class="pagination-controls-wrapper">
                        <span>Page <?= $current_page_historique ?> sur <?= $total_pages_historique ?> (Total: <?= $total_historique_entries ?> entrées)</span>
                        <nav class="pagination-nav">
                            <?php 
                            $q_params_hist = $_GET; 
                            if ($current_page_historique > 1): $q_params_hist['page'] = $current_page_historique - 1;?><a href="?<?= http_build_query($q_params_hist) ?>" class="page-link">« Préc.</a><?php else: ?><span class="page-link disabled">« Préc.</span><?php endif; 
                            $num_links = 2; 
                            $start = max(1, $current_page_historique - $num_links); $end = min($total_pages_historique, $current_page_historique + $num_links);
                            if ($start > 1) { $q_params_hist['page'] = 1; echo '<a href="?'.http_build_query($q_params_hist).'" class="page-link">1</a>'; if ($start > 2) { echo '<span class="ellipsis">…</span>'; } }
                            for ($i = $start; $i <= $end; $i++): $q_params_hist['page'] = $i; $active = ($i == $current_page_historique) ? 'active' : ''; ?><a href="?<?= http_build_query($q_params_hist) ?>" class="page-link <?= $active ?>"><?= $i ?></a><?php endfor; 
                            if ($end < $total_pages_historique) { if ($end < $total_pages_historique - 1) { echo '<span class="ellipsis">…</span>'; } $q_params_hist['page'] = $total_pages_historique; echo '<a href="?'.http_build_query($q_params_hist).'" class="page-link">'.$total_pages_historique.'</a>'; }
                            if ($current_page_historique < $total_pages_historique): $q_params_hist['page'] = $current_page_historique + 1;?><a href="?<?= http_build_query($q_params_hist) ?>" class="page-link">Suiv. »</a><?php else: ?><span class="page-link disabled">Suiv. »</span><?php endif; ?>
                        </nav>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="no-messages text-center">Aucune action enregistrée pour les filtres sélectionnés.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>

<footer class="site-footer admin-footer">
   <div class="container"><p class="copyright-text text-center">© <span id="footer-year"><?= date('Y') ?></span> SANTE TV - Espace Administration.</p></div>
</footer>

<script src="../assets/js/script.js"></script> 
<style>
    .body-admin-historique .page-main-title .page-icon { color: var(--color-neutral-dark); }
    .body-admin-historique .filters-toolbar { border-top: 3px solid var(--color-neutral-dark); }
    .body-admin-historique .data-table thead th { background-color: var(--color-neutral-lightest); color: var(--color-neutral-darkest); border-bottom: 2px solid var(--color-neutral-medium); }
    .body-admin-historique .pagination-nav .page-link.active { background-color: var(--color-neutral-darkest); border-color: var(--color-neutral-darkest); }
    .body-admin-historique .pagination-nav .page-link:hover { border-color: var(--color-neutral-dark); color: var(--color-neutral-darkest); }
    .body-admin-historique .btn.primary-action { background-color: var(--color-neutral-darkest); }
    .body-admin-historique .btn.primary-action:hover { background-color: var(--color-neutral-dark); }
    .details-list-hist { list-style-type: none; padding-left: 0; font-size: 0.85em; }
    .details-list-hist li { margin-bottom: 0.25em; }
    .details-list-hist li strong { color: var(--color-neutral-medium); }
</style>
</body>
</html>