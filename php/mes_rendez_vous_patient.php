<?php
session_start();
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 

if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'patient') {
    $_SESSION['flash_message_login'] = "Accès non autorisé. Veuillez vous connecter."; 
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/connexion.php'); 
    exit;
}
$patient_id = $_SESSION['utilisateur_id'];

$stmt_patient_data_rdv_page = $pdo->prepare("SELECT nom, prenom FROM patients WHERE id = ?");
$stmt_patient_data_rdv_page->execute([$patient_id]);
$patient_data_rdv_page = $stmt_patient_data_rdv_page->fetch(PDO::FETCH_ASSOC);
$nom_patient_display_rdv_page = $patient_data_rdv_page ? htmlspecialchars($patient_data_rdv_page['prenom'] . ' ' . $patient_data_rdv_page['nom']) : htmlspecialchars($_SESSION['nom'] ?? 'Patient');

try {
    $statuts_a_marquer_vus_rdv_list = ['confirmé', 'annulé'];
    $check_enum_stmt_rdv_list = $pdo->query("SHOW COLUMNS FROM rendez_vous LIKE 'statut'");
    $enum_definition_rdv_list = $check_enum_stmt_rdv_list->fetch(PDO::FETCH_ASSOC);
    $enum_values_rdv_list = [];
    if ($enum_definition_rdv_list && preg_match_all("/'([^']+)'/", $enum_definition_rdv_list['Type'], $matches)) {
        $enum_values_rdv_list = $matches[1];
    }
    if (in_array('refusé', $enum_values_rdv_list)) { 
        $statuts_a_marquer_vus_rdv_list[] = 'refusé';
    }

    if (!empty($statuts_a_marquer_vus_rdv_list)) {
        $in_clause_placeholders_rdv_list = implode(',', array_fill(0, count($statuts_a_marquer_vus_rdv_list), '?'));
        $sql_mark_seen_rdv_list = "UPDATE rendez_vous SET vue_par_patient = 1 
                                   WHERE patient_id = ? AND statut IN ($in_clause_placeholders_rdv_list) AND vue_par_patient = 0 AND supprime_par_patient = 0";
        
        $stmt_mark_seen_rdv_list = $pdo->prepare($sql_mark_seen_rdv_list);
        $params_mark_seen_rdv_list = array_merge([$patient_id], $statuts_a_marquer_vus_rdv_list);
        $stmt_mark_seen_rdv_list->execute($params_mark_seen_rdv_list);
    }
} catch (PDOException $e) {
    error_log("Erreur marquage RDV vus (mes_rendez_vous_patient) pour patient $patient_id: " . $e->getMessage());
}

$stmt_rdv_list_patient = $pdo->prepare("
    SELECT 
        rv.id, rv.date_rdv, rv.heure_rdv, rv.statut, rv.motif_annulation, rv.vue_par_patient,
        m.nom AS medecin_nom, m.prenom AS medecin_prenom, m.specialite AS medecin_specialite,
        m.adresse AS medecin_adresse, 
        CONCAT(rv.date_rdv, ' ', rv.heure_rdv) AS datetime_rdv_str,
        CASE 
            WHEN rv.statut = 'confirmé' AND CONCAT(rv.date_rdv, ' ', rv.heure_rdv) >= NOW() THEN 1 
            WHEN rv.statut = 'en attente' AND CONCAT(rv.date_rdv, ' ', rv.heure_rdv) >= NOW() THEN 2 
            WHEN rv.statut = 'confirmé' AND CONCAT(rv.date_rdv, ' ', rv.heure_rdv) < NOW() THEN 3  
            WHEN rv.statut = 'refusé' THEN 4 
            WHEN rv.statut = 'annulé' THEN 5 
            ELSE 6 
        END AS sort_priority
    FROM rendez_vous rv
    JOIN medecins m ON rv.medecin_id = m.id
    WHERE rv.patient_id = :patient_id AND rv.supprime_par_patient = 0
    ORDER BY 
        sort_priority ASC, 
        CASE WHEN sort_priority <= 2 THEN UNIX_TIMESTAMP(CONCAT(rv.date_rdv, ' ', rv.heure_rdv)) ELSE NULL END ASC,
        CASE WHEN sort_priority > 2 THEN UNIX_TIMESTAMP(CONCAT(rv.date_rdv, ' ', rv.heure_rdv)) ELSE NULL END DESC,
        rv.heure_rdv ASC
");
$stmt_rdv_list_patient->execute([':patient_id' => $patient_id]);
$rendezvous_liste_patient = $stmt_rdv_list_patient->fetchAll(PDO::FETCH_ASSOC);

$motifs_annulation_json_mes_rdv = [];
foreach ($rendezvous_liste_patient as $rdv_item_motif_mes_rdv) {
    if (!empty($rdv_item_motif_mes_rdv['motif_annulation'])) {
        $motifs_annulation_json_mes_rdv[$rdv_item_motif_mes_rdv['id']] = $rdv_item_motif_mes_rdv['motif_annulation'];
    }
}

$flash_message_mes_rdv_page = $_SESSION['flash_message'] ?? null;
$flash_type_mes_rdv_page = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$csrf_token_actions_rdv_patient = generate_csrf_token(); 

$stmt_rdv_nav_pat = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE patient_id = :id AND ((date_rdv > CURDATE()) OR (date_rdv = CURDATE() AND heure_rdv >= CURTIME())) AND statut IN ('en attente', 'confirmé') AND supprime_par_patient = 0");
$stmt_rdv_nav_pat->execute([':id' => $patient_id]);
$nb_rdv_nav_pat = $stmt_rdv_nav_pat->fetchColumn();

$nb_notif_nav_pat = 0; 
$table_notif_exists = $pdo->query("SHOW TABLES LIKE 'notifications_patients'")->rowCount() > 0;
if ($table_notif_exists) {
    $stmt_notif_nav_pat = $pdo->prepare("SELECT COUNT(*) FROM notifications_patients WHERE patient_id = :id AND lu = 0");
    $stmt_notif_nav_pat->execute([':id' => $patient_id]);
    $nb_notif_nav_pat = $stmt_notif_nav_pat->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Rendez-vous - <?= $nom_patient_display_rdv_page ?> - SANTE TV</title>
    <meta name="description" content="Consultez l'historique de vos rendez-vous médicaux et gérez vos prochaines consultations sur SANTE TV.">
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tiny.cloud/1/4amskh9mj3cii8jraizm25oi6k5kvplfgmzr48dm2b52fgj7/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body class="body-page-mes-rdv">

<header class="site-header">
    <div class="container">
        <div class="logo-branding"><a href="../index.php" title="Accueil SANTE TV"><img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img"><span class="site-title">SANTE TV</span></a></div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav"><span></span><span></span><span></span></button>
        <nav class="main-navigation" id="main-nav">
             <ul>
                <li><a href="dashboard_patient.php" class="nav-link">Mon Espace</a></li>
                <li><a href="../pages/docteurs.php" class="nav-link">Trouver un Médecin</a></li>
                <li><a href="mes_rendez_vous_patient.php" class="nav-link active">Mes Rendez-vous
                    <?php if($nb_rdv_nav_pat > 0): ?><span class="badge-notification"><?= $nb_rdv_nav_pat ?></span><?php endif; ?>
                </a></li>
                <li><a href="messages_patient.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'messages_patient.php') ? 'active' : ''; ?>"> Notifications
                    <?php if(isset($nombre_notifications_non_lues) && $nombre_notifications_non_lues > 0): // Adaptez le nom de la variable si besoin ?>
                    <span class="badge-notification"><?= $nombre_notifications_non_lues ?></span>
                    <?php endif; ?>
                    </a>
                </li>
                <li><a href="profil_patient.php" class="nav-link">Mon Profil</a></li>
                <li><a href="deconnexion.php" class="nav-link btn btn-sm btn-danger">Déconnexion</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content user-dashboard-page section-padding">
    <div class="container">
        <div class="page-header">
            <h1 class="page-main-title">Historique de Mes Rendez-vous</h1>
            <a href="../pages/docteurs.php" class="btn primary-action">
                <i class="fas fa-calendar-plus icon-left"></i>Prendre un Nouveau Rendez-vous
            </a>
        </div>

        <?php if ($flash_message_mes_rdv_page): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_mes_rdv_page) ?> alert-dismissible">
                <?= htmlspecialchars($flash_message_mes_rdv_page) ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer" onclick="this.parentElement.style.display='none';">×</button>
            </div>
        <?php endif; ?>

        <?php if (count($rendezvous_liste_patient) > 0): ?>
            <div class="table-responsive-wrapper">
                <table class="data-table rdv-table">
                    <thead>
                        <tr>
                            <th>Médecin</th>
                            <th>Spécialité</th>
                            <th>Date & Heure</th>
                            <th>Statut</th>
                            <th>Lieu (indicatif)</th>
                            <th class="actions-cell">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rendezvous_liste_patient as $rdv): ?>
                        <?php
                            $datetime_rdv_obj_pat = new DateTime($rdv['datetime_rdv_str']);
                            $maintenant_obj_pat = new DateTime();
                            $is_past_rdv_pat = $datetime_rdv_obj_pat < $maintenant_obj_pat;
                            $can_cancel_rdv_pat = !$is_past_rdv_pat && 
                                         in_array($rdv['statut'], ['en attente', 'confirmé']) &&
                                         ($datetime_rdv_obj_pat->getTimestamp() - $maintenant_obj_pat->getTimestamp()) >= (24 * 60 * 60);
                            $statut_lower_rdv_pat = strtolower($rdv['statut']);
                            $statut_class_for_css_pat = str_replace(' ', '-', $statut_lower_rdv_pat);
                            if ($statut_lower_rdv_pat === 'refusé' && !in_array('refusé', $enum_values_rdv_list)) {
                                $statut_class_for_css_pat = 'annulé';
                            }
                            $row_class_pat = '';
                            if ($is_past_rdv_pat && !in_array($statut_lower_rdv_pat, ['annulé', 'refusé'])) $row_class_pat .= ' rdv-past';
                            if (!$rdv['vue_par_patient'] && in_array($statut_lower_rdv_pat, ['confirmé', 'annulé', 'refusé'])) $row_class_pat .= ' rdv-unread';
                        ?>
                        <tr class="<?= trim($row_class_pat) ?>" id="rdv-row-<?= $rdv['id'] ?>">
                            <td data-label="Médecin"><?= htmlspecialchars($rdv['medecin_prenom'] . ' ' . $rdv['medecin_nom']) ?></td>
                            <td data-label="Spécialité"><?= htmlspecialchars($rdv['medecin_specialite']) ?></td>
                            <td data-label="Date & Heure"><?= date('d/m/Y', $datetime_rdv_obj_pat->getTimestamp()) ?> à <?= date('H:i', $datetime_rdv_obj_pat->getTimestamp()) ?></td>
                            <td data-label="Statut">
                                <span class="status-badge status-<?= htmlspecialchars($statut_class_for_css_pat) ?>">
                                    <?= htmlspecialchars(ucfirst($rdv['statut'])) ?>
                                </span>
                            </td>
                            <td data-label="Lieu"><?= htmlspecialchars($rdv['medecin_adresse'] ? mb_strimwidth($rdv['medecin_adresse'], 0, 30, "...") : 'N/A') ?></td>
                            <td class="actions-cell">
                                <?php if ($can_cancel_rdv_pat): ?>
                                    <button type="button" class="btn btn-sm btn-warning open-annulation-modal-patient" 
                                            data-rdv-id="<?= $rdv['id'] ?>" 
                                            title="Annuler ce rendez-vous">
                                        <i class="fas fa-times-circle"></i> Annuler
                                    </button>
                                <?php elseif (in_array($rdv['statut'], ['en attente', 'confirmé']) && !$is_past_rdv_pat && !$can_cancel_rdv_pat): ?>
                                    <small class="text-muted" title="Les annulations doivent être faites au moins 24h à l'avance.">Annulation tardive</small>
                                <?php elseif (in_array($statut_lower_rdv_pat, ['annulé', 'refusé']) && !empty($rdv['motif_annulation'])): ?>
                                     <button type="button" class="btn btn-sm btn-info open-motif-info-modal-patient" 
                                             data-rdv-id="<?= $rdv['id'] ?>" 
                                             title="Voir le motif d'annulation/refus">
                                        <i class="fas fa-info-circle"></i> Motif
                                    </button>
                                <?php elseif ($is_past_rdv_pat && !in_array($rdv['statut'], ['annulé', 'refusé'])) : ?>
                                    <form action="supprimer_rdv_historique.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_actions_rdv_patient) ?>">
                                        <input type="hidden" name="rdv_id" value="<?= $rdv['id'] ?>">
                                        <input type="hidden" name="user_type" value="patient">
                                        <button type="submit" class="btn btn-xs btn-danger"
                                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce rendez-vous de votre historique ? Cette action est irréversible pour vous.');"
                                                title="Supprimer ce rendez-vous de l'historique">
                                            <i class="fas fa-trash-alt"></i> Supprimer
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center" style="margin-top: 2rem;">
                <i class="fas fa-info-circle icon-left"></i>Vous n'avez aucun rendez-vous programmé ou passé pour le moment.
            </div>
        <?php endif; ?>
    </div>
</main>

<div id="annulationRdvModalPatient" class="modal" role="dialog" aria-modal="true" aria-labelledby="titleModalAnnulationRdvPatient">
    <div class="modal-content">
        <button class="close-modal-button" data-close-modal aria-label="Fermer">×</button> 
        <h3 class="form-title" id="titleModalAnnulationRdvPatient">Annuler le Rendez-vous</h3>
        <form id="formAnnulationRdvPatient" action="annuler_rdv.php" method="GET" class="user-form"> 
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_actions_rdv_patient) ?>">
            <input type="hidden" id="rdvIdAnnulationInputPatient" name="id"> 
            <div class="form-group">
                <label for="motifAnnulationTextareaPatient">Motif de votre annulation (minimum 10 mots) : <span class="text-danger">*</span></label>
                <textarea id="motifAnnulationTextareaPatient" name="motif" rows="4" class="form-control" required></textarea> 
                <small id="wordCountMotifPatient" class="form-note"></small>
                <small class="form-error-message" id="error-motifAnnulationPatient"></small>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-danger"><i class="fas fa-check icon-left"></i>Confirmer l'Annulation</button>
                <button type="button" class="btn secondary-action" data-close-modal>Fermer</button>
            </div>
        </form>
    </div>
</div>

<div id="motifAnnulationInfoModalPatient" class="modal" role="dialog" aria-modal="true" aria-labelledby="titleModalMotifInfoPatient">
    <div class="modal-content">
        <button class="close-modal-button" data-close-modal aria-label="Fermer">×</button>
        <h3 class="form-title" id="titleModalMotifInfoPatient">Motif d'Annulation ou de Refus</h3>
        <div id="motifInfoContentPatient" class="content-box" style="padding: 10px; background: #f4f4f4; border-radius: 4px; white-space: pre-wrap; min-height: 50px;">
        </div>
         <div class="form-actions" style="text-align:right; margin-top:1rem;">
             <button type="button" class="btn secondary-action" data-close-modal>Fermer</button>
        </div>
    </div>
</div>

<footer class="site-footer">
   <div class="container"><p class="copyright-text text-center">© <span id="footer-year"></span> SANTE TV Plus. Tous droits réservés.</p></div>
</footer>

<script src="../assets/js/script.js"></script> 
<script>
const motifsAnnulationGlobauxMesRdvPage = <?= json_encode($motifs_annulation_json_mes_rdv, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
</script>
</body>
</html>