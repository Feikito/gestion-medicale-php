<?php
session_start();
require '../php/db.php'; 

if (!isset($_SESSION['admin_id'])) { 
    $_SESSION['flash_message_login'] = "Accès refusé. Veuillez vous connecter.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/admin-login.php'); 
    exit;
}
// $admin_id_gest_com = $_SESSION['admin_id']; // Non utilisé directement

// $stmt_admin_info_gest_com = $pdo->prepare("SELECT nom FROM admins WHERE id = ?");
// $stmt_admin_info_gest_com->execute([$admin_id_gest_com]);
// $admin_data_gest_com = $stmt_admin_info_gest_com->fetch();
// $admin_nom_display_gest_com = $admin_data_gest_com ? htmlspecialchars($admin_data_gest_com['nom']) : 'Administrateur'; // Non utilisé dans HTML

// S'assurer que la table commentaires existe
$table_commentaires_exists = $pdo->query("SHOW TABLES LIKE 'commentaires'")->rowCount() > 0;

$commentaires_list_view_admin = [];
$total_commentaires_for_pagination = 0;
$total_pages_for_pagination_com = 0;
$current_page_com_admin = 1; // Initialisation
$statuts_com_enum_list = ['en attente', 'validé', 'refusé']; // Fallback
$has_email_col_com = false;

$status_filter_com = $_GET['status_com'] ?? ''; 

if ($table_commentaires_exists) {
    $sql_com_base_admin = "SELECT * FROM commentaires WHERE 1=1";
    $params_com_query_admin = [];
    $count_params_com_query_admin = [];

    if (!empty($status_filter_com) && in_array($status_filter_com, ['en attente', 'validé', 'refusé'])) {
        $sql_com_base_admin .= " AND statut = :status_filter";
        $params_com_query_admin[':status_filter'] = $status_filter_com;
        $count_params_com_query_admin[':status_filter'] = $status_filter_com;
    }

    $sql_com_order_admin = " ORDER BY FIELD(statut, 'en attente', 'validé', 'refusé'), date_commentaire DESC";

    $per_page_com_admin = 15; 
    $current_page_com_admin = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ["options" => ["min_range"=>1]]) ? (int)$_GET['page'] : 1;
    $offset_com_admin = ($current_page_com_admin - 1) * $per_page_com_admin;

    $count_sql_com_total_admin = "SELECT COUNT(*) FROM commentaires WHERE 1=1";
    if (!empty($status_filter_com)) { 
        $count_sql_com_total_admin .= " AND statut = :status_filter"; 
    }
    $stmt_count_com_total_admin = $pdo->prepare($count_sql_com_total_admin);
    $stmt_count_com_total_admin->execute($count_params_com_query_admin);
    $total_commentaires_for_pagination = (int)$stmt_count_com_total_admin->fetchColumn();
    $total_pages_for_pagination_com = $total_commentaires_for_pagination > 0 ? ceil($total_commentaires_for_pagination / $per_page_com_admin) : 0;

    $sql_com_final_admin = $sql_com_base_admin . $sql_com_order_admin . " LIMIT :limit OFFSET :offset";
    $params_com_query_admin[':limit'] = $per_page_com_admin;
    $params_com_query_admin[':offset'] = $offset_com_admin;

    $stmt_commentaires_list_admin = $pdo->prepare($sql_com_final_admin);
    foreach ($params_com_query_admin as $key => &$val_com_admin) {
        $stmt_commentaires_list_admin->bindValue($key, $val_com_admin, (is_int($val_com_admin) || $key === ':limit' || $key === ':offset' ? PDO::PARAM_INT : PDO::PARAM_STR));
    }
    unset($val_com_admin);
    $stmt_commentaires_list_admin->execute();
    $commentaires_list_view_admin = $stmt_commentaires_list_admin->fetchAll(PDO::FETCH_ASSOC);

    try { 
        $result_enum = $pdo->query("SHOW COLUMNS FROM commentaires LIKE 'statut'")->fetch();
        if ($result_enum && preg_match_all("/'([^']+)'/", $result_enum['Type'], $matches_enum)) {
             $statuts_com_enum_list = $matches_enum[1];
             // S'assurer que l'ordre est correct pour l'affichage du filtre
             usort($statuts_com_enum_list, function($a, $b) {
                $order = ['en attente' => 1, 'validé' => 2, 'refusé' => 3];
                return ($order[$a] ?? 99) <=> ($order[$b] ?? 99);
             });
        }
    } catch(PDOException $e) { error_log("Erreur recup ENUM Commentaires: ".$e->getMessage()); }

    try {
        $comment_cols_query = $pdo->query("DESCRIBE commentaires");
        if ($comment_cols_query) {
            $comment_cols = $comment_cols_query->fetchAll(PDO::FETCH_COLUMN);
            $has_email_col_com = in_array('email', $comment_cols);
        }
    } catch (PDOException $e) {
        error_log("Erreur DESCRIBE commentaires: " . $e->getMessage());
    }
} else {
     $_SESSION['flash_message'] = "La table des commentaires semble manquante. Veuillez contacter le support technique.";
     $_SESSION['flash_type'] = "error";
}

$flash_message_gest_com_page = $_SESSION['flash_message'] ?? null;
$flash_type_gest_com_page = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Badges de navigation
$nb_med_att_nav_gest_com = 0;
$nb_com_att_nav_gest_com = 0; // Recalculé ici
if ($pdo->query("SHOW TABLES LIKE 'medecins'")->rowCount() > 0) {
    try { $nb_med_att_nav_gest_com = $pdo->query("SELECT COUNT(*) FROM medecins WHERE valide = 0")->fetchColumn(); }
    catch (PDOException $e) { error_log("Erreur comptage medecins en attente (gest_com): " . $e->getMessage()); }
}
if ($table_commentaires_exists) { // Utiliser la variable déjà définie
    try { $nb_com_att_nav_gest_com = $pdo->query("SELECT COUNT(*) FROM commentaires WHERE statut = 'en attente'")->fetchColumn(); }
    catch (PDOException $e) { error_log("Erreur comptage commentaires en attente (gest_com): " . $e->getMessage()); }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commentaires - Admin SANTE TV</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tiny.cloud/1/4amskh9mj3cii8jraizm25oi6k5kvplfgmzr48dm2b52fgj7/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body class="admin-gestion-page">

<header class="site-header admin-header">
    <div class="container">
        <div class="logo-branding"><a href="dashboard_admin.php" title="Tableau de Bord Admin"><img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img"><span class="site-title">Admin SANTE TV</span></a></div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav"><span></span><span></span><span></span></button>
        <nav class="main-navigation admin-navigation" id="main-nav">
             <ul>
                <li><a href="dashboard_admin.php" class="nav-link">Dashboard</a></li>
                <li><a href="gestion_medecins.php" class="nav-link">Médecins <?php if($nb_med_att_nav_gest_com > 0): ?><span class="badge-notification"><?= $nb_med_att_nav_gest_com ?></span><?php endif; ?></a></li>
                <li><a href="gestion_patients.php" class="nav-link">Patients</a></li>
                <li><a href="envoyer_emails_masse.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'envoyer_emails_masse.php' || basename($_SERVER['PHP_SELF']) == 'envoyer_email_specifique.php') ? 'active' : ''; ?>"> <i class="fas fa-mail-bulk icon-left"></i>Email en Masse</a></li>
                <li><a href="gestion_commentaires.php" class="nav-link active">Commentaires <?php if($nb_com_att_nav_gest_com > 0): ?><span class="badge-notification"><?= $nb_com_att_nav_gest_com ?></span><?php endif; ?></a></li>
                <li><a href="deconnexion_admin.php" class="nav-link btn btn-sm btn-danger">Déconnexion</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content admin-page-container section-padding"> 
    <div class="container">
        <div class="page-header">
            <h1 class="page-main-title">Gestion des Commentaires des Utilisateurs</h1>
            <p class="section-subtitle">Validez, refusez ou supprimez les commentaires soumis sur la plateforme.</p>
        </div>

        <?php if ($flash_message_gest_com_page): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_gest_com_page) ?> alert-dismissible">
                <?= htmlspecialchars($flash_message_gest_com_page) ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button> 
            </div>
        <?php endif; ?>

        <div class="filters-toolbar">
            <form method="GET" action="gestion_commentaires.php" class="filter-form-inline">
                 <div class="form-group filter-group">
                    <label for="status_filter_com_select" class="sr-only">Filtrer par statut</label>
                    <select id="status_filter_com_select" name="status_com" class="form-control">
                        <option value="">Tous les Statuts</option>
                        <?php foreach ($statuts_com_enum_list as $statut_option): ?>
                        <option value="<?= htmlspecialchars($statut_option) ?>" <?= ($status_filter_com === $statut_option) ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($statut_option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn primary-action filter-submit-button"><i class="fas fa-filter icon-left"></i>Filtrer</button>
                <?php if (!empty($status_filter_com)): ?>
                    <a href="gestion_commentaires.php" class="btn secondary-action filter-reset-button"><i class="fas fa-undo icon-left"></i>Réinitialiser</a>
                <?php endif; ?>
            </form>
        </div>

        <section class="comments-management-table">
             <?php if (count($commentaires_list_view_admin) > 0): ?>
                <div class="table-responsive-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom Auteur</th>
                                <?php if ($has_email_col_com): ?>
                                <th>Email Auteur</th>
                                <?php endif; ?>
                                <th>Commentaire</th>
                                <th>Date Soumission</th>
                                <th>Statut</th>
                                <th class="actions-cell">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($commentaires_list_view_admin as $com_item): ?>
                            <tr id="comment-row-<?= $com_item['id'] ?>">
                                <td><?= $com_item['id'] ?></td>
                                <td><?= htmlspecialchars($com_item['nom']) ?></td>
                                <?php if ($has_email_col_com): ?>
                                <td><?= htmlspecialchars($com_item['email'] ?? 'N/A') ?></td>
                                <?php endif; ?>
                                <!-- STYLE: max-width, white-space, word-break inline -->
                                <td style="max-width: 300px; white-space: pre-wrap; word-break: break-word;"><?= nl2br(htmlspecialchars($com_item['contenu'])) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($com_item['date_commentaire'])) ?></td>
                                <?php $statut_com_admin_css = 'statut-' . strtolower(str_replace(' ', '-', $com_item['statut'])); ?>
                                <td><span class="status-badge <?= htmlspecialchars($statut_com_admin_css) ?>"><?= htmlspecialchars(ucfirst($com_item['statut'])) ?></span></td>
                                <td class="actions-cell">
                                    <?php if ($com_item['statut'] === 'en attente'): ?>
                                        <a href="valider_commentaire.php?id=<?= $com_item['id'] ?>&return_url=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-xs btn-success" title="Valider ce commentaire"><i class="fas fa-check"></i></a>
                                        <a href="rejeter_commentaire.php?id=<?= $com_item['id'] ?>&return_url=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-xs btn-warning" title="Refuser ce commentaire"><i class="fas fa-times"></i></a>
                                    <?php endif; ?>
                                    <a href="supprimer_commentaire.php?id=<?= $com_item['id'] ?>&return_url=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                       class="btn btn-xs btn-danger" 
                                       onclick="return confirm('Supprimer définitivement ce commentaire ?')" 
                                       title="Supprimer ce commentaire">
                                       <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                 <?php if ($total_pages_for_pagination_com > 1): ?>
                <div class="pagination-controls-wrapper">
                    <span>Page <?= $current_page_com_admin ?> sur <?= $total_pages_for_pagination_com ?> (Total: <?= $total_commentaires_for_pagination ?> commentaires)</span>
                    <nav class="pagination-nav">
                        <?php $q_params_com_admin_page = $_GET; if ($current_page_com_admin > 1): $q_params_com_admin_page['page'] = $current_page_com_admin - 1;?><a href="?<?= http_build_query($q_params_com_admin_page) ?>" class="page-link">« Préc.</a><?php else: ?><span class="page-link disabled">« Préc.</span><?php endif; 
                        $num_links_com_pagination = 2; 
                        $start_page_com = max(1, $current_page_com_admin - $num_links_com_pagination);
                        $end_page_com = min($total_pages_for_pagination_com, $current_page_com_admin + $num_links_com_pagination);
                        if ($start_page_com > 1) { $q_params_com_admin_page['page'] = 1; echo '<a href="?'.http_build_query($q_params_com_admin_page).'" class="page-link">1</a>'; if ($start_page_com > 2) { echo '<span class="ellipsis">…</span>'; } }
                        for ($i_com_page = $start_page_com; $i_com_page <= $end_page_com; $i_com_page++): 
                            $q_params_com_admin_page['page'] = $i_com_page; 
                            $active_class_com_admin = ($i_com_page == $current_page_com_admin) ? 'active' : ''; ?>
                            <a href="?<?= http_build_query($q_params_com_admin_page) ?>" class="page-link <?= $active_class_com_admin ?>"><?= $i_com_page ?></a>
                        <?php endfor; 
                        if ($end_page_com < $total_pages_for_pagination_com) { if ($end_page_com < $total_pages_for_pagination_com - 1) { echo '<span class="ellipsis">…</span>'; } $q_params_com_admin_page['page'] = $total_pages_for_pagination_com; echo '<a href="?'.http_build_query($q_params_com_admin_page).'" class="page-link">'.$total_pages_for_pagination_com.'</a>'; }
                        if ($current_page_com_admin < $total_pages_for_pagination_com): $q_params_com_admin_page['page'] = $current_page_com_admin + 1;?><a href="?<?= http_build_query($q_params_com_admin_page) ?>" class="page-link">Suiv. »</a><?php else: ?><span class="page-link disabled">Suiv. »</span><?php endif; ?>
                    </nav>
                </div>
                <?php endif; ?>

            <?php else: ?>
                 <p class="no-messages text-center">
                    <?php if (!$table_commentaires_exists): ?>
                        La table des commentaires est inaccessible ou n'existe pas.
                    <?php elseif (!empty($status_filter_com)): ?>
                        Aucun commentaire ne correspond à vos filtres.
                    <?php else: ?>
                        Aucun commentaire n'a été soumis pour le moment.
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </section>
        
    </div>
</main>

<footer class="site-footer admin-footer">
   <div class="container"><p class="copyright-text text-center">© <span id="footer-year"></span> SANTE TV - Espace Administration.</p></div>
</footer>

<script src="../assets/js/script.js"></script> 

</body>
</html>