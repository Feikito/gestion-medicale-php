<?php
session_start();
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 

if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'medecin') {
    $_SESSION['flash_message_login'] = "Accès non autorisé."; 
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/connexion.php'); 
    exit;
}
$medecin_id = $_SESSION['utilisateur_id'];

$stmt_med_info_agenda = $pdo->prepare("SELECT nom, prenom, valide FROM medecins WHERE id = ?");
$stmt_med_info_agenda->execute([$medecin_id]);
$medecin_user_data_agenda = $stmt_med_info_agenda->fetch(PDO::FETCH_ASSOC);

if (!$medecin_user_data_agenda) { 
    session_unset(); session_destroy(); 
    header('Location: ../pages/connexion.php'); exit;
}
if ($medecin_user_data_agenda['valide'] != 1) {
    $_SESSION['flash_message'] = "Votre compte est en attente de validation administrateur pour accéder à cette fonctionnalité.";
    $_SESSION['flash_type'] = "warning";
    header('Location: espace_medecin.php'); 
    exit;
}
$nom_medecin_display_header_agenda = htmlspecialchars("Dr. " . ($medecin_user_data_agenda['prenom'] ?? '') . ' ' . ($medecin_user_data_agenda['nom'] ?? 'Médecin'));

try {
    $statuts_rdv_a_marquer_vus_med_list_agenda = ['en attente', 'annulé'];
    $check_enum_rdv_agenda = $pdo->query("SHOW COLUMNS FROM rendez_vous LIKE 'statut'");
    $enum_def_rdv_agenda = $check_enum_rdv_agenda->fetch(PDO::FETCH_ASSOC);
    $enum_values_rdv_list_agenda = []; 
    if ($enum_def_rdv_agenda && preg_match_all("/'([^']+)'/", $enum_def_rdv_agenda['Type'], $matches_agenda)) {
        $enum_values_rdv_list_agenda = $matches_agenda[1];
    }
    
    if (!empty($statuts_rdv_a_marquer_vus_med_list_agenda)) {
        $in_clause_rdv_med_list_agenda = implode(',', array_fill(0, count($statuts_rdv_a_marquer_vus_med_list_agenda), '?'));
        $sql_mark_rdv_seen_med_list_agenda = "UPDATE rendez_vous SET vue_par_medecin = 1 
                                             WHERE medecin_id = ? AND statut IN ($in_clause_rdv_med_list_agenda) AND vue_par_medecin = 0 AND supprime_par_medecin = 0";
        $stmt_mark_rdv_seen_med_list_agenda = $pdo->prepare($sql_mark_rdv_seen_med_list_agenda);
        $params_mark_rdv_seen_med_list_agenda = array_merge([$medecin_id], $statuts_rdv_a_marquer_vus_med_list_agenda);
        $stmt_mark_rdv_seen_med_list_agenda->execute($params_mark_rdv_seen_med_list_agenda);
    }
} catch (PDOException $e) { 
    error_log("Erreur marquage RDV vus (mes_rendez_vous_medecin) pour medecin $medecin_id: " . $e->getMessage()); 
}

$filtre_statut_rdv_page_med = $_GET['statut'] ?? '';
$filtre_periode_rdv_page_med = $_GET['periode'] ?? '';
$search_patient_rdv_page_med = trim($_GET['search_patient_rdv'] ?? '');

$sql_liste_rdv_page_med = "SELECT 
            rv.id, rv.date_rdv, rv.heure_rdv, rv.statut, rv.motif_annulation,
            p.id AS patient_id_rdv, p.nom AS patient_nom, p.prenom AS patient_prenom, p.email AS patient_email, 
            p.photo AS patient_photo, 
            (SELECT telephone FROM patients WHERE id = p.id LIMIT 1) AS patient_telephone, 
            rv.vue_par_medecin, CONCAT(rv.date_rdv, ' ', rv.heure_rdv) AS datetime_rdv,
            CASE 
                WHEN rv.statut = 'en attente' AND CONCAT(rv.date_rdv, ' ', rv.heure_rdv) >= NOW() THEN 1
                WHEN rv.statut = 'confirmé' AND CONCAT(rv.date_rdv, ' ', rv.heure_rdv) >= NOW() THEN 2
                WHEN rv.statut = 'confirmé' AND CONCAT(rv.date_rdv, ' ', rv.heure_rdv) < NOW() THEN 3
                WHEN rv.statut = 'refusé' THEN 4 
                WHEN rv.statut = 'annulé' THEN 5 
                ELSE 6 
            END AS sort_priority
        FROM rendez_vous rv JOIN patients p ON rv.patient_id = p.id
        WHERE rv.medecin_id = :medecin_id AND rv.supprime_par_medecin = 0";
$params_liste_rdv_page_med = [':medecin_id' => $medecin_id];

if (!empty($filtre_statut_rdv_page_med) && in_array($filtre_statut_rdv_page_med, ['en attente', 'confirmé', 'annulé', 'refusé'])) {
    $sql_liste_rdv_page_med .= " AND rv.statut = :statut_filtre";
    $params_liste_rdv_page_med[':statut_filtre'] = $filtre_statut_rdv_page_med;
}
if (!empty($search_patient_rdv_page_med)) {
    $sql_liste_rdv_page_med .= " AND (CONCAT(p.nom, ' ', p.prenom) LIKE :search_patient OR p.email LIKE :search_patient OR CONCAT(p.prenom, ' ', p.nom) LIKE :search_patient)";
    $params_liste_rdv_page_med[':search_patient'] = "%$search_patient_rdv_page_med%";
}
$today_date_page_med = date('Y-m-d');
if ($filtre_periode_rdv_page_med === 'today') { 
    $sql_liste_rdv_page_med .= " AND rv.date_rdv = :today_filter"; 
    $params_liste_rdv_page_med[':today_filter'] = $today_date_page_med; 
} elseif ($filtre_periode_rdv_page_med === 'week') { 
    $next_week_date_page_med = date('Y-m-d', strtotime('+6 days')); 
    $sql_liste_rdv_page_med .= " AND rv.date_rdv BETWEEN :today_filter AND :next_week_filter"; 
    $params_liste_rdv_page_med[':today_filter'] = $today_date_page_med; 
    $params_liste_rdv_page_med[':next_week_filter'] = $next_week_date_page_med;
} elseif ($filtre_periode_rdv_page_med === 'month') { 
    $next_month_date_page_med = date('Y-m-d', strtotime('+29 days')); 
    $sql_liste_rdv_page_med .= " AND rv.date_rdv BETWEEN :today_filter AND :next_month_filter"; 
    $params_liste_rdv_page_med[':today_filter'] = $today_date_page_med; 
    $params_liste_rdv_page_med[':next_month_filter'] = $next_month_date_page_med;
} elseif ($filtre_periode_rdv_page_med === 'past') { 
    $sql_liste_rdv_page_med .= " AND CONCAT(rv.date_rdv, ' ', rv.heure_rdv) < NOW()"; 
}
$sql_liste_rdv_page_med .= " ORDER BY sort_priority ASC, rv.date_rdv ASC, rv.heure_rdv ASC";

$per_page_rdv_med_list_agenda = 10;
$current_page_rdv_med_list_agenda = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ["options" => ["min_range"=>1]]) ? (int)$_GET['page'] : 1;
$offset_rdv_med_list_agenda = ($current_page_rdv_med_list_agenda - 1) * $per_page_rdv_med_list_agenda;

$count_sql_rdv_med_list_base_agenda = "SELECT COUNT(rv.id) FROM rendez_vous rv JOIN patients p ON rv.patient_id = p.id WHERE rv.medecin_id = :medecin_id AND rv.supprime_par_medecin = 0";
$count_params_rdv_med_list_agenda_page = [':medecin_id' => $medecin_id]; 
if (!empty($filtre_statut_rdv_page_med)) { $count_sql_rdv_med_list_base_agenda .= " AND rv.statut = :statut_filtre"; $count_params_rdv_med_list_agenda_page[':statut_filtre'] = $filtre_statut_rdv_page_med;}
if (!empty($search_patient_rdv_page_med)) { $count_sql_rdv_med_list_base_agenda .= " AND (CONCAT(p.nom, ' ', p.prenom) LIKE :search_patient OR p.email LIKE :search_patient OR CONCAT(p.prenom, ' ', p.nom) LIKE :search_patient)"; $count_params_rdv_med_list_agenda_page[':search_patient'] = "%$search_patient_rdv_page_med%";}
if ($filtre_periode_rdv_page_med === 'today') { $count_sql_rdv_med_list_base_agenda .= " AND rv.date_rdv = :today_filter"; $count_params_rdv_med_list_agenda_page[':today_filter'] = $today_date_page_med; }
elseif ($filtre_periode_rdv_page_med === 'week') { $count_sql_rdv_med_list_base_agenda .= " AND rv.date_rdv BETWEEN :today_filter AND :next_week_filter"; $count_params_rdv_med_list_agenda_page[':today_filter'] = $today_date_page_med; $count_params_rdv_med_list_agenda_page[':next_week_filter'] = $next_week_date_page_med; }
elseif ($filtre_periode_rdv_page_med === 'month') { $count_sql_rdv_med_list_base_agenda .= " AND rv.date_rdv BETWEEN :today_filter AND :next_month_filter"; $count_params_rdv_med_list_agenda_page[':today_filter'] = $today_date_page_med; $count_params_rdv_med_list_agenda_page[':next_month_filter'] = $next_month_date_page_med; }
elseif ($filtre_periode_rdv_page_med === 'past') { $count_sql_rdv_med_list_base_agenda .= " AND CONCAT(rv.date_rdv, ' ', rv.heure_rdv) < NOW()"; }

$stmt_count_rdv_med_list_agenda = $pdo->prepare($count_sql_rdv_med_list_base_agenda);
$stmt_count_rdv_med_list_agenda->execute($count_params_rdv_med_list_agenda_page);
$total_rdv_med_list_agenda = $stmt_count_rdv_med_list_agenda->fetchColumn();
$total_pages_rdv_med_list_agenda = $total_rdv_med_list_agenda > 0 ? ceil($total_rdv_med_list_agenda / $per_page_rdv_med_list_agenda) : 0;

$sql_liste_rdv_page_med .= " LIMIT :limit OFFSET :offset";
$params_liste_rdv_page_med[':limit'] = $per_page_rdv_med_list_agenda;
$params_liste_rdv_page_med[':offset'] = $offset_rdv_med_list_agenda;

$stmt_rdv_page_list_final = $pdo->prepare($sql_liste_rdv_page_med);
foreach ($params_liste_rdv_page_med as $key => &$value) { 
    $stmt_rdv_page_list_final->bindValue($key, $value, (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR));
}
unset($value); 
$stmt_rdv_page_list_final->execute();
$rendezvous_medecin_page_liste_final = $stmt_rdv_page_list_final->fetchAll(PDO::FETCH_ASSOC);

$motifs_annul_json_mes_rdv_med = [];
foreach ($rendezvous_medecin_page_liste_final as $rdv_item_motif_med_agenda) {
    if (!empty($rdv_item_motif_med_agenda['motif_annulation'])) {
        $motifs_annul_json_mes_rdv_med[$rdv_item_motif_med_agenda['id']] = $rdv_item_motif_med_agenda['motif_annulation'];
    }
}

$flash_message_page_med_rdv_list = $_SESSION['flash_message'] ?? null;
$flash_type_page_med_rdv_list = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$stmt_rdv_att_nav_med = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE medecin_id = :id AND statut = 'en attente' AND ( (date_rdv > CURDATE()) OR (date_rdv = CURDATE() AND heure_rdv >= CURTIME()) ) AND supprime_par_medecin = 0");
$stmt_rdv_att_nav_med->execute([':id' => $medecin_id]);
$nb_rdv_att_nav_med = $stmt_rdv_att_nav_med->fetchColumn();
$nb_msg_nav_med = 0; 
$table_messages_exists_nav = $pdo->query("SHOW TABLES LIKE 'messages'")->rowCount() > 0;
if ($table_messages_exists_nav) {
    $check_col_stmt_msg_med = $pdo->query("SHOW COLUMNS FROM messages LIKE 'lu_par_medecin'"); 
    if ($check_col_stmt_msg_med->fetch()) { 
        $stmt_msg_nav_med = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = :medecin_id AND lu_par_medecin = 0"); 
        $stmt_msg_nav_med->execute([':medecin_id' => $medecin_id]); 
        $nb_msg_nav_med = $stmt_msg_nav_med->fetchColumn(); 
    }
}

$csrf_token_actions_rdv_med = generate_csrf_token(); 
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Rendez-vous - <?= $nom_medecin_display_header_agenda ?> - SANTE TV</title>
    <meta name="description" content="Gérez et consultez tous vos rendez-vous médicaux, les demandes en attente, les confirmations et l'historique sur SANTE TV.">
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tiny.cloud/1/4amskh9mj3cii8jraizm25oi6k5kvplfgmzr48dm2b52fgj7/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body class="body-page-mes-rdv-medecin"> 

<header class="site-header">
    <div class="container">
        <div class="logo-branding"><a href="../index.php" title="Accueil SANTE TV"><img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img"><span class="site-title">SANTE TV</span></a></div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav"><span></span><span></span><span></span></button>
        <nav class="main-navigation" id="main-nav">
            <ul>
                <li><a href="espace_medecin.php" class="nav-link">Mon Espace</a></li>
                <li><a href="mes_rendez_vous_medecin.php" class="nav-link active">Mes Rendez-vous
                     <?php if($nb_rdv_att_nav_med > 0): ?><span class="badge-notification"><?= $nb_rdv_att_nav_med ?></span><?php endif; ?>
                </a></li>
                <li><a href="gestion_disponibilites_medecin.php" class="nav-link">Mes Disponibilités</a></li>
                <li>
                    <a href="messages_medecin.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'messages_medecin.php') ? 'active' : ''; ?>">Messagerie Reçue
                        <?php if(isset($nb_messages_non_lus_med_dash) && $nb_messages_non_lus_med_dash > 0 && $compte_medecin_est_valide): // Adaptez le nom de la variable si besoin ?>
                        <span class="badge-notification"><?= $nb_messages_non_lus_med_dash ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="profil_medecin.php" class="nav-link">Mon Profil Pro.</a></li>
                <li><a href="deconnexion.php" class="nav-link btn btn-sm btn-danger">Déconnexion</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content user-dashboard-page section-padding">
    <div class="container">
        <div class="page-header">
            <h1 class="page-main-title">Gestion de Mes Rendez-vous</h1>
            <p class="section-subtitle">Consultez, confirmez, refusez ou annulez vos rendez-vous programmés.</p>
        </div>

        <?php if ($flash_message_page_med_rdv_list): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_page_med_rdv_list) ?> alert-dismissible">
                <?= htmlspecialchars($flash_message_page_med_rdv_list) ?>
                <button type="button" class="close-alert" data-dismiss="alert">×</button>
            </div>
        <?php endif; ?>

        <div class="filters-toolbar">
            <form method="GET" action="mes_rendez_vous_medecin.php" class="filter-form-inline">
                <div class="form-group search-group"><label for="search_patient_rdv_page_input" class="sr-only">Patient</label><input type="search" id="search_patient_rdv_page_input" name="search_patient_rdv" placeholder="Nom ou Email du Patient..." value="<?= htmlspecialchars($search_patient_rdv_page_med) ?>" class="form-control"></div>
                <div class="form-group filter-group"><label for="filtre_statut_rdv_med_page" class="sr-only">Statut</label><select id="filtre_statut_rdv_med_page" name="statut" class="form-control"><option value="">Tous les Statuts</option><option value="en attente" <?= ($filtre_statut_rdv_page_med === 'en attente') ? 'selected' : '' ?>>En attente</option><option value="confirmé" <?= ($filtre_statut_rdv_page_med === 'confirmé') ? 'selected' : '' ?>>Confirmé</option><option value="annulé" <?= ($filtre_statut_rdv_page_med === 'annulé') ? 'selected' : '' ?>>Annulé</option><option value="refusé" <?= ($filtre_statut_rdv_page_med === 'refusé') ? 'selected' : '' ?>>Refusé</option></select></div>
                <div class="form-group filter-group"><label for="filtre_periode_rdv_med_page" class="sr-only">Période</label><select id="filtre_periode_rdv_med_page" name="periode" class="form-control"><option value="">Toutes Périodes</option><option value="today" <?= ($filtre_periode_rdv_page_med === 'today') ? 'selected' : '' ?>>Aujourd'hui</option><option value="week" <?= ($filtre_periode_rdv_page_med === 'week') ? 'selected' : '' ?>>7 Prochains Jours</option><option value="month" <?= ($filtre_periode_rdv_page_med === 'month') ? 'selected' : '' ?>>30 Prochains Jours</option><option value="past" <?= ($filtre_periode_rdv_page_med === 'past') ? 'selected' : '' ?>>Rendez-vous Passés</option></select></div>
                <button type="submit" class="btn primary-action filter-submit-button"><i class="fas fa-filter icon-left"></i>Filtrer</button>
                <?php if (!empty($search_patient_rdv_page_med) || !empty($filtre_statut_rdv_page_med) || !empty($filtre_periode_rdv_page_med)): ?><a href="mes_rendez_vous_medecin.php" class="btn secondary-action filter-reset-button"><i class="fas fa-undo icon-left"></i>Réinitialiser</a><?php endif; ?>
            </form>
        </div>

        <?php if (count($rendezvous_medecin_page_liste_final) > 0): ?>
            <div class="table-responsive-wrapper">
                <table class="data-table rdv-table">
                    <thead><tr><th>Patient</th><th>Contact</th><th>Date</th><th>Heure</th><th>Statut</th><th class="actions-cell">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($rendezvous_medecin_page_liste_final as $rdv): ?>
                        <?php
                            $dt_rdv_med = new DateTime($rdv['datetime_rdv']);
                            $is_past_rdv_med = $dt_rdv_med < (new DateTime());
                            $can_med_cancel_confirmed_rdv = !$is_past_rdv_med && $rdv['statut'] === 'confirmé' && ($dt_rdv_med->getTimestamp() - time()) >= (24*60*60); 
                            $statut_css_med = strtolower(str_replace(' ', '-', $rdv['statut']));
                             if ($statut_css_med === 'refusé' && !($enum_def_rdv_agenda && strpos($enum_def_rdv_agenda['Type'], "'refusé'") !== false)) {
                                $statut_css_med = 'annulé'; 
                            }
                            $row_css_class_med = ($is_past_rdv_med && !in_array($rdv['statut'], ['annulé', 'refusé'])) ? 'rdv-past' : '';
                            if (!$rdv['vue_par_medecin'] && ($rdv['statut'] === 'en attente' || ($rdv['statut'] === 'annulé' && !empty($rdv['motif_annulation'])))) $row_css_class_med .= ' rdv-unread';
                        ?>
                        <tr class="<?= trim($row_css_class_med) ?>" id="rdv-med-row-<?= $rdv['id'] ?>">
                            <td data-label="Patient">
                                <img src="<?= $rdv['patient_photo'] ? '../' . htmlspecialchars($rdv['patient_photo']) : '../assets/images/placeholder-patient.png' ?>" alt="Photo patient" class="table-avatar-img">
                                <?= htmlspecialchars($rdv['patient_prenom'] . ' ' . $rdv['patient_nom']) ?>
                            </td>
                            <td data-label="Contact">
                                <a href="mailto:<?= htmlspecialchars($rdv['patient_email']) ?>" title="Envoyer un email"><i class="fas fa-envelope"></i></a>
                                <?php if (!empty($rdv['patient_telephone'])): ?>
                                    <br><a href="tel:<?= htmlspecialchars($rdv['patient_telephone']) ?>" title="Appeler"><i class="fas fa-phone"></i> <?= htmlspecialchars($rdv['patient_telephone']) ?></a>
                                <?php endif; ?>
                            </td>
                            <td data-label="Date"><?= date('d/m/Y', $dt_rdv_med->getTimestamp()) ?></td>
                            <td data-label="Heure"><?= date('H:i', $dt_rdv_med->getTimestamp()) ?></td>
                            <td data-label="Statut"><span class="status-badge status-<?= $statut_css_med ?>"><?= htmlspecialchars(ucfirst($rdv['statut'])) ?></span></td>
                            <td class="actions-cell">
                                <?php if ($rdv['statut'] === 'en attente' && !$is_past_rdv_med): ?>
                                    <a href="gerer_demande_rdv.php?id=<?= $rdv['id'] ?>&action=accepter" class="btn btn-sm btn-success" title="Accepter ce RDV"><i class="fas fa-check"></i></a>
                                    <button type="button" class="btn btn-sm btn-danger" data-modal-target="#refusRdvMedecinModal" data-rdv-id="<?= $rdv['id'] ?>" title="Refuser ce RDV"><i class="fas fa-times"></i></button>
                                <?php elseif ($can_med_cancel_confirmed_rdv): ?>
                                     <button type="button" class="btn btn-sm btn-warning" data-modal-target="#annulationRdvMedecinModal" data-rdv-id="<?= $rdv['id'] ?>" title="Annuler ce RDV confirmé"><i class="fas fa-calendar-times"></i></button>
                                <?php elseif (in_array($rdv['statut'], ['annulé', 'refusé']) && !empty($rdv['motif_annulation'])): ?>
                                     <button type="button" class="btn btn-sm btn-info" data-modal-target="#motifRdvInfoModalMedecin" data-rdv-id="<?= $rdv['id'] ?>" title="Voir Motif"><i class="fas fa-info-circle"></i></button>
                                <?php elseif ($is_past_rdv_med && !in_array($rdv['statut'], ['annulé', 'refusé'])) : ?>
                                    <form action="supprimer_rdv_historique.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_actions_rdv_med) ?>">
                                        <input type="hidden" name="rdv_id" value="<?= $rdv['id'] ?>">
                                        <input type="hidden" name="user_type" value="medecin">
                                        <button type="submit" class="btn btn-xs btn-danger"
                                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce rendez-vous de votre historique ? Cette action est irréversible pour vous.');"
                                                title="Supprimer ce rendez-vous de l'historique">
                                            <i class="fas fa-trash-alt"></i> Supprimer
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_pages_rdv_med_list_agenda > 1): ?>
            <div class="pagination-controls-wrapper">
                <span>Page <?= $current_page_rdv_med_list_agenda ?> sur <?= $total_pages_rdv_med_list_agenda ?> (Total: <?= $total_rdv_med_list_agenda ?> RDV)</span>
                <nav class="pagination-nav">
                    <?php $q_params_page_med = $_GET; if ($current_page_rdv_med_list_agenda > 1): $q_params_page_med['page'] = $current_page_rdv_med_list_agenda - 1;?><a href="?<?= http_build_query($q_params_page_med) ?>" class="page-link">« Préc.</a><?php else: ?><span class="page-link disabled">« Préc.</span><?php endif; for ($i_page_med = 1; $i_page_med <= $total_pages_rdv_med_list_agenda; $i_page_med++): $q_params_page_med['page'] = $i_page_med;?><a href="?<?= http_build_query($q_params_page_med) ?>" class="page-link <?= ($i_page_med == $current_page_rdv_med_list_agenda) ? 'active' : '' ?>"><?= $i_page_med ?></a><?php endfor; if ($current_page_rdv_med_list_agenda < $total_pages_rdv_med_list_agenda): $q_params_page_med['page'] = $current_page_rdv_med_list_agenda + 1;?><a href="?<?= http_build_query($q_params_page_med) ?>" class="page-link">Suiv. »</a><?php else: ?><span class="page-link disabled">Suiv. »</span><?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="no-messages text-center">Aucun rendez-vous trouvé pour les filtres sélectionnés.</p>
        <?php endif; ?>
    </div>
</main>

<div id="refusRdvMedecinModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="titleModalRefusMed">
    <div class="modal-content">
        <button class="close-modal-button" aria-label="Fermer">×</button>
        <h3 class="form-title" id="titleModalRefusMed">Refuser la Demande de Rendez-vous</h3>
        <form id="formRefusRdvMedecin" action="gerer_demande_rdv.php" method="GET" class="user-form"> 
             <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_actions_rdv_med) ?>">
            <input type="hidden" name="action" value="refuser">
            <input type="hidden" id="rdvIdRefusInputMedecin" name="id">
            <div class="form-group">
                <label for="motifRefusTextareaMedecin">Motif du refus (recommandé, sera visible par le patient) :</label>
                <textarea id="motifRefusTextareaMedecin" name="motif" rows="4" class="form-control" placeholder="Ex: Indisponibilité exceptionnelle..."></textarea>
            </div>
            <div class="form-actions" style="display:flex; justify-content:space-between;">
                <button type="button" class="btn secondary-action" data-close-modal>Annuler</button>
                <button type="submit" class="btn btn-danger"><i class="fas fa-times-circle icon-left"></i>Confirmer le Refus</button>
            </div>
        </form>
    </div>
</div>

<div id="annulationRdvMedecinModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="titleModalAnnulMed">
    <div class="modal-content">
        <button class="close-modal-button" aria-label="Fermer">×</button>
        <h3 class="form-title" id="titleModalAnnulMed">Annuler un Rendez-vous Confirmé</h3>
        <form id="formAnnulationRdvMedecin" action="annuler_rdv.php" method="GET" class="user-form"> 
             <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_actions_rdv_med) ?>">
            <input type="hidden" id="rdvIdAnnulationInputMedecin" name="id">
            <div class="form-group">
                <label for="motifAnnulationTextareaMedecin">Motif de l'annulation (minimum 10 mots, sera visible par le patient) : <span class="text-danger">*</span></label>
                <textarea id="motifAnnulationTextareaMedecin" name="motif" rows="4" class="form-control" required></textarea>
                <small id="wordCountMotifMedecin" class="form-note"></small>
                <small class="form-error-message" id="error-motifAnnulationMedecin"></small>
            </div>
            <div class="form-actions" style="display:flex; justify-content:space-between;">
                <button type="button" class="btn secondary-action" data-close-modal>Annuler</button>
                <button type="submit" class="btn btn-danger"><i class="fas fa-calendar-times icon-left"></i>Confirmer l'Annulation</button>
            </div>
        </form>
    </div>
</div>

<div id="motifRdvInfoModalMedecin" class="modal" role="dialog" aria-modal="true" aria-labelledby="titleModalMotifInfoMed">
    <div class="modal-content">
        <button class="close-modal-button" aria-label="Fermer">×</button>
        <h3 class="form-title" id="titleModalMotifInfoMed">Motif d'Annulation ou de Refus</h3>
        <div id="motifInfoContentMedecin" class="content-box"></div>
        <div class="form-actions" style="text-align:right; margin-top:1rem;"><button type="button" class="btn secondary-action" data-close-modal>Fermer</button></div>
    </div>
</div>

<footer class="site-footer">
   <div class="container"><p class="copyright-text text-center">© <span id="footer-year"></span> SANTE TV Plus. Tous droits réservés.</p></div>
</footer>

<script src="../assets/js/script.js"></script> 
<script>
    const motifsGlobauxRdvMedecinPageList = <?= json_encode($motifs_annul_json_mes_rdv_med, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
</script>
</body>
</html>