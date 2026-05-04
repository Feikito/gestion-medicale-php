<?php
session_start();
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 

// 1. Vérification médecin connecté et validé
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'medecin') {
    $_SESSION['flash_message_login'] = "Accès non autorisé."; // Message pour la page de connexion
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/connexion.php'); 
    exit;
}
$medecin_id_dispo = $_SESSION['utilisateur_id'];

$stmt_med_info_dispo = $pdo->prepare("SELECT nom, prenom, valide FROM medecins WHERE id = ?");
$stmt_med_info_dispo->execute([$medecin_id_dispo]);
$medecin_data_dispo = $stmt_med_info_dispo->fetch(PDO::FETCH_ASSOC);

if (!$medecin_data_dispo) { 
    session_unset(); session_destroy(); 
    header('Location: ../pages/connexion.php'); exit;
}
if ($medecin_data_dispo['valide'] != 1) {
    $_SESSION['flash_message'] = "Votre compte doit être validé par un administrateur pour gérer vos disponibilités.";
    $_SESSION['flash_type'] = "warning";
    header('Location: espace_medecin.php'); 
    exit;
}
$nom_medecin_display_dispo_header = htmlspecialchars("Dr. " . ($medecin_data_dispo['prenom'] ?? '') . ' ' . ($medecin_data_dispo['nom'] ?? 'Médecin'));

// 2. Jours de la semaine
$jours_semaine_map_dispo = [
    1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 
    4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 0 => 'Dimanche'
];
$ordre_affichage_jours_dispo = [1, 2, 3, 4, 5, 6, 0]; 

// 3. Récupérer les disponibilités régulières actuelles
$stmt_dispos_reg_list = $pdo->prepare(
    "SELECT id, jour_semaine, TIME_FORMAT(heure_debut, '%H:%i') AS heure_debut_formatee, 
            TIME_FORMAT(heure_fin, '%H:%i') AS heure_fin_formatee, type_plage 
     FROM disponibilites_medecin 
     WHERE medecin_id = :medecin_id 
     ORDER BY jour_semaine ASC, type_plage ASC, heure_debut_formatee ASC"
);
$stmt_dispos_reg_list->execute([':medecin_id' => $medecin_id_dispo]);
$dispos_reg_db_list = $stmt_dispos_reg_list->fetchAll(PDO::FETCH_ASSOC);

$dispos_par_jour_view = array_fill(0, 7, ['travail' => [], 'pause' => []]);
foreach ($dispos_reg_db_list as $dispo_item_view) {
    $dispos_par_jour_view[$dispo_item_view['jour_semaine']][$dispo_item_view['type_plage']][] = $dispo_item_view;
}

// 4. Récupérer les exceptions d'horaires
$date_limite_exceptions_view = date('Y-m-d', strtotime('-7 days')); 
$exceptions_horaires_view_list = [];
if ($pdo->query("SHOW TABLES LIKE 'exceptions_horaires_medecin'")->rowCount() > 0) {
    $stmt_exceptions_list_view = $pdo->prepare(
        "SELECT id, date_exception, TIME_FORMAT(heure_debut, '%H:%i') AS heure_debut_formatee, 
                TIME_FORMAT(heure_fin, '%H:%i') AS heure_fin_formatee, type_exception, motif 
         FROM exceptions_horaires_medecin 
         WHERE medecin_id = :medecin_id AND date_exception >= :date_limite
         ORDER BY date_exception ASC, heure_debut_formatee ASC 
         LIMIT 20"
    );
    $stmt_exceptions_list_view->execute([':medecin_id' => $medecin_id_dispo, ':date_limite' => $date_limite_exceptions_view]);
    $exceptions_horaires_view_list = $stmt_exceptions_list_view->fetchAll(PDO::FETCH_ASSOC);
}

// 5. Données et erreurs pour les formulaires
$form_data_dispo_reg_page = $_SESSION['form_data_dispo'] ?? [];
$form_errors_dispo_reg_page = $_SESSION['form_errors_dispo'] ?? [];
unset($_SESSION['form_data_dispo'], $_SESSION['form_errors_dispo']);

$form_data_exception_add_page = $_SESSION['form_data_exception'] ?? [];
$form_errors_exception_add_page = $_SESSION['form_errors_exception'] ?? [];
unset($_SESSION['form_data_exception'], $_SESSION['form_errors_exception']);

$flash_message_dispo_page = $_SESSION['flash_message'] ?? null;
$flash_type_dispo_page = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$csrf_token_dispo_reg = generate_csrf_token();
$csrf_token_exception_add = $csrf_token_dispo_reg; 

// Badges de navigation
$stmt_rdv_att_nav_dispo = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE medecin_id = :id AND statut = 'en attente' AND ( (date_rdv > CURDATE()) OR (date_rdv = CURDATE() AND heure_rdv >= CURTIME()) )");
$stmt_rdv_att_nav_dispo->execute([':id' => $medecin_id_dispo]);
$nb_rdv_att_nav_dispo = $stmt_rdv_att_nav_dispo->fetchColumn();

$nb_msg_nav_dispo = 0; 
$table_messages_exists_dispo_nav = $pdo->query("SHOW TABLES LIKE 'messages'")->rowCount() > 0;
if ($table_messages_exists_dispo_nav) {
    $check_col_msg_dispo = $pdo->query("SHOW COLUMNS FROM messages LIKE 'lu_par_medecin'"); 
    if ($check_col_msg_dispo->fetch()) { 
        $stmt_msg_nav_dispo = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = :med_id AND lu_par_medecin = 0"); 
        $stmt_msg_nav_dispo->execute([':med_id' => $medecin_id_dispo]); 
        $nb_msg_nav_dispo = $stmt_msg_nav_dispo->fetchColumn(); 
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de Mes Disponibilités - <?= $nom_medecin_display_dispo_header ?> - SANTE TV</title>
    <meta name="description" content="Définissez et gérez vos plages horaires de travail régulières et vos absences exceptionnelles sur SANTE TV.">
    <!-- Ce fichier est dans php/, donc assets/ est à ../assets/ -->
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tiny.cloud/1/4amskh9mj3cii8jraizm25oi6k5kvplfgmzr48dm2b52fgj7/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body class="profile-page body-gestion-dispo"> 

<header class="site-header">
    <div class="container">
        <!-- Lien vers index.php à la racine -->
        <div class="logo-branding"><a href="../index.php" title="Accueil SANTE TV"><img src="../assets/images/logo1.png" id="logo-img"><span class="site-title">SANTE TV</span></a></div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav"><span></span><span></span><span></span></button>
        <nav class="main-navigation" id="main-nav">
            <ul>
                <li><a href="espace_medecin.php" class="nav-link">Mon Espace</a></li>
                <li><a href="mes_rendez_vous_medecin.php" class="nav-link">Mes Rendez-vous <?php if($nb_rdv_att_nav_dispo > 0): ?><span class="badge-notification"><?= $nb_rdv_att_nav_dispo ?></span><?php endif; ?></a></li>
                <li><a href="gestion_disponibilites_medecin.php" class="nav-link active">Mes Disponibilités</a></li>
                <li>
                    <a href="messages_medecin.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'messages_medecin.php') ? 'active' : ''; ?>">Messagerie Reçue
                        <?php if(isset($nb_messages_non_lus_med_dash) && $nb_messages_non_lus_med_dash > 0 && $compte_medecin_est_valide): // Adaptez le nom de la variable si besoin ?>
                        <span class="badge-notification"><?= $nb_messages_non_lus_med_dash ?></span>
                        <?php endif; ?>
                    </a>
                </li>                <li><a href="profil_medecin.php" class="nav-link">Mon Profil</a></li>
                <li><a href="deconnexion.php" class="nav-link btn btn-sm btn-danger">Déconnexion</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content section-padding">
    <div class="container">
        <div class="page-header">
            <h1 class="page-main-title">Gestion de Mes Disponibilités</h1>
            <a href="espace_medecin.php" class="btn btn-sm secondary-action">← Retour à Mon Espace</a>
        </div>

        <?php if ($flash_message_dispo_page): ?>
            <!-- STYLE: margin-bottom inline -->
            <div class="alert alert-<?= htmlspecialchars($flash_type_dispo_page) ?> alert-dismissible" style="margin-bottom:1.5rem;">
                <?= htmlspecialchars($flash_message_dispo_page) ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button>
            </div>
        <?php endif; ?>

        <div class="profile-content-grid">
            <section id="horairesReguliersSection" class="profile-section card availability-management-section">
                <h2 class="section-title"><i class="fas fa-calendar-alt icon-left"></i>Mes Horaires Réguliers</h2>
                <!-- STYLE: margin-bottom inline -->
                <p class="form-note" style="margin-bottom:1rem;">Définissez vos plages de travail et de pause récurrentes pour chaque jour de la semaine.</p>
                
                <div class="form-section-box" id="formAddDispoReguliereWrapper"> 
                    <h3 id="formDispoTitle" class="form-subtitle"><i class="fas fa-plus-circle icon-left"></i>Ajouter une Plage Horaire</h3>
                    <form id="formAddDispoReguliere" action="ajouter_dispo.php" method="POST" class="user-form">
                        <?= csrf_input_field() ?>
                        <input type="hidden" name="form_origin_dispo" value="gestion_disponibilites_medecin.php">
                        <?php if (isset($form_errors_dispo_reg_page['_general'])): ?><p class="alert alert-danger"><?= htmlspecialchars($form_errors_dispo_reg_page['_general']) ?></p><?php endif; ?>

                        <div class="form-dispo-grid">
                            <div class="form-group">
                                <label for="dispo_jour_semaine">Jour : <span class="text-danger">*</span></label>
                                <select id="dispo_jour_semaine" name="jour_semaine" class="form-control <?= isset($form_errors_dispo_reg_page['jour_semaine']) ? 'input-error' : '' ?>" required>
                                    <?php foreach ($ordre_affichage_jours_dispo as $num_jour_select): ?>
                                        <option value="<?= $num_jour_select ?>" <?= (isset($form_data_dispo_reg_page['jour_semaine']) && $form_data_dispo_reg_page['jour_semaine'] == $num_jour_select) ? 'selected' : '' ?>><?= $jours_semaine_map_dispo[$num_jour_select] ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-error-message"><?= htmlspecialchars($form_errors_dispo_reg_page['jour_semaine'] ?? '') ?></small>
                            </div>
                            <div class="form-group">
                                <label for="dispo_heure_debut">De : <span class="text-danger">*</span></label>
                                <input type="time" id="dispo_heure_debut" name="heure_debut" class="form-control <?= isset($form_errors_dispo_reg_page['heure_debut']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_dispo_reg_page['heure_debut'] ?? '') ?>" required step="1800">
                                <small class="form-error-message"><?= htmlspecialchars($form_errors_dispo_reg_page['heure_debut'] ?? '') ?></small>
                            </div>
                            <div class="form-group">
                                <label for="dispo_heure_fin">À : <span class="text-danger">*</span></label>
                                <input type="time" id="dispo_heure_fin" name="heure_fin" class="form-control <?= isset($form_errors_dispo_reg_page['heure_fin']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_dispo_reg_page['heure_fin'] ?? '') ?>" required step="1800">
                                <small class="form-error-message"><?= htmlspecialchars($form_errors_dispo_reg_page['heure_fin'] ?? '') ?></small>
                            </div>
                            <div class="form-group">
                                <label for="dispo_type_plage">Type : <span class="text-danger">*</span></label>
                                <select id="dispo_type_plage" name="type_plage" class="form-control <?= isset($form_errors_dispo_reg_page['type_plage']) ? 'input-error' : '' ?>" required>
                                    <option value="travail" <?= (isset($form_data_dispo_reg_page['type_plage']) && $form_data_dispo_reg_page['type_plage'] === 'travail') ? 'selected' : '' ?>>Travail</option>
                                    <option value="pause" <?= (isset($form_data_dispo_reg_page['type_plage']) && $form_data_dispo_reg_page['type_plage'] === 'pause') ? 'selected' : '' ?>>Pause</option>
                                </select>
                                <small class="form-error-message"><?= htmlspecialchars($form_errors_dispo_reg_page['type_plage'] ?? '') ?></small>
                            </div>
                            <!-- STYLE: align-self inline -->
                            <div class="form-actions" style="align-self: flex-end;">
                                <button type="submit" class="btn primary-action"><i class="fas fa-plus-circle icon-left"></i>Ajouter</button>
                            </div>
                        </div>
                        <!-- STYLE: display, margin-top inline -->
                        <small class="form-error-message error-message-display" style="display:<?= isset($form_errors_dispo_reg_page['overlap']) || isset($form_errors_dispo_reg_page['time_order']) ? 'block' : 'none' ?>; margin-top:0.75rem;">
                            <?= htmlspecialchars($form_errors_dispo_reg_page['overlap'] ?? ($form_errors_dispo_reg_page['time_order'] ?? '')) ?>
                        </small>
                    </form>
                </div>
                <!-- STYLE: margin inline -->
                <hr style="margin: 2rem 0;">
                <?php 
                $hasAnyDispoReg = false;
                foreach ($ordre_affichage_jours_dispo as $num_j) { if (!empty($dispos_par_jour_view[$num_j]['travail']) || !empty($dispos_par_jour_view[$num_j]['pause'])) { $hasAnyDispoReg = true; break; } }
                if (!$hasAnyDispoReg): ?>
                    <p class="no-slots-message text-center">Vous n'avez pas encore défini d'horaires réguliers.</p>
                <?php else: ?>
                    <?php foreach ($ordre_affichage_jours_dispo as $num_jour_affic_dispo): ?>
                    <div class="availability-day-group">
                        <h3><?= $jours_semaine_map_dispo[$num_jour_affic_dispo] ?></h3>
                        <h4 class="availability-type-title">Plages de Travail :</h4>
                        <?php if (!empty($dispos_par_jour_view[$num_jour_affic_dispo]['travail'])): ?>
                            <ul class="list-unstyled time-slots-list">
                                <?php foreach ($dispos_par_jour_view[$num_jour_affic_dispo]['travail'] as $plage_travail): ?>
                                    <li class="time-slot-entry">
                                        <span class="slot-time"><i class="far fa-clock icon-left"></i><?= htmlspecialchars($plage_travail['heure_debut_formatee']) ?> - <?= htmlspecialchars($plage_travail['heure_fin_formatee']) ?></span>
                                        <span class="slot-actions">
                                            <a href="supprimer_dispo.php?id=<?= $plage_travail['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Supprimer cette plage de travail ?')" title="Supprimer"><i class="fas fa-trash-alt"></i></a>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?><p class="no-slots-message text-muted"><i>Aucune plage de travail définie.</i></p><?php endif; ?>

                        <!-- STYLE: margin-top inline -->
                        <h4 class="availability-type-title" style="margin-top:0.75rem;">Pauses :</h4>
                        <?php if (!empty($dispos_par_jour_view[$num_jour_affic_dispo]['pause'])): ?>
                            <ul class="list-unstyled time-slots-list">
                                <?php foreach ($dispos_par_jour_view[$num_jour_affic_dispo]['pause'] as $plage_pause): ?>
                                    <li class="time-slot-entry">
                                        <span class="slot-time"><i class="fas fa-mug-hot icon-left"></i><?= htmlspecialchars($plage_pause['heure_debut_formatee']) ?> - <?= htmlspecialchars($plage_pause['heure_fin_formatee']) ?></span>
                                        <span class="slot-actions">
                                            <a href="supprimer_dispo.php?id=<?= $plage_pause['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Supprimer cette plage de pause ?')" title="Supprimer"><i class="fas fa-trash-alt"></i></a>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?><p class="no-slots-message text-muted"><i>Aucune pause définie.</i></p><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section id="exceptionsHorairesSection" class="profile-section card availability-management-section">
                <h2 class="section-title"><i class="fas fa-calendar-times icon-left"></i>Absences et Exceptions Horaires</h2>
                <!-- STYLE: margin-bottom inline -->
                <p class="form-note" style="margin-bottom:1rem;">Indiquez vos indisponibilités ponctuelles (congés, urgences) ou des plages de travail exceptionnelles.</p>
                <div class="form-section-box" id="formAddExceptionWrapper">
                    <h3 class="form-subtitle"><i class="fas fa-plus-circle icon-left"></i>Ajouter une Exception</h3>
                    <form id="formAddException" action="ajouter_exception.php" method="POST" class="user-form">
                        <?= csrf_input_field() ?>
                        <input type="hidden" name="form_origin_exception" value="gestion_disponibilites_medecin.php">
                        <?php if (isset($form_errors_exception_add_page['_general'])): ?><p class="alert alert-danger"><?= htmlspecialchars($form_errors_exception_add_page['_general']) ?></p><?php endif; ?>

                        <div class="form-group">
                            <label for="exc_date_exception">Date de l'exception : <span class="text-danger">*</span></label>
                            <input type="date" id="exc_date_exception" name="date_exception" class="form-control <?= isset($form_errors_exception_add_page['date_exception']) ? 'input-error' : '' ?>" 
                                   value="<?= htmlspecialchars($form_data_exception_add_page['date_exception'] ?? '') ?>" required min="<?= date('Y-m-d') ?>">
                            <small class="form-error-message"><?= htmlspecialchars($form_errors_exception_add_page['date_exception'] ?? '') ?></small>
                        </div>
                        <div class="form-group">
                            <label for="exc_type_exception">Type d'exception : <span class="text-danger">*</span></label>
                            <select id="exc_type_exception" name="type_exception" class="form-control <?= isset($form_errors_exception_add_page['type_exception']) ? 'input-error' : '' ?>" required>
                                <option value="non_travaille" <?= (isset($form_data_exception_add_page['type_exception']) && $form_data_exception_add_page['type_exception'] === 'non_travaille') ? 'selected' : '' ?>>Journée Non Travaillée (Congé, Absence)</option>
                                <option value="indisponible" <?= (isset($form_data_exception_add_page['type_exception']) && $form_data_exception_add_page['type_exception'] === 'indisponible') ? 'selected' : '' ?>>Plage Horaire Indisponible (Pause, etc.)</option>
                                <option value="travail_exceptionnel" <?= (isset($form_data_exception_add_page['type_exception']) && $form_data_exception_add_page['type_exception'] === 'travail_exceptionnel') ? 'selected' : '' ?>>Plage Horaire Travaillée (Exceptionnellement)</option>
                                <option value="pause_exceptionnelle" <?= (isset($form_data_exception_add_page['type_exception']) && $form_data_exception_add_page['type_exception'] === 'pause_exceptionnelle') ? 'selected' : '' ?>>Pause (Exceptionnelle)</option>
                            </select>
                            <small class="form-error-message"><?= htmlspecialchars($form_errors_exception_add_page['type_exception'] ?? '') ?></small>
                        </div>

                        <!-- STYLE: display inline (dépend du PHP, donc difficile à externaliser complètement sans JS pour l'état initial) -->
                        <div id="time_fields_exception_form" class="exception-form-grid" style="<?= (!isset($form_data_exception_add_page['type_exception']) || (isset($form_data_exception_add_page['type_exception']) && $form_data_exception_add_page['type_exception'] === 'non_travaille' && empty($form_data_exception_add_page['heure_debut_exception']))) ? 'display:none;' : 'display:grid;' ?>;">
                            <div class="form-group">
                                <label for="exc_heure_debut">De (si plage spécifique) :</label>
                                <input type="time" id="exc_heure_debut" name="heure_debut_exception" class="form-control <?= isset($form_errors_exception_add_page['heure_debut_exception']) ? 'input-error' : '' ?>" 
                                       value="<?= htmlspecialchars($form_data_exception_add_page['heure_debut_exception'] ?? '') ?>" step="1800">
                                <small class="form-error-message"><?= htmlspecialchars($form_errors_exception_add_page['heure_debut_exception'] ?? '') ?></small>
                            </div>
                            <div class="form-group">
                                <label for="exc_heure_fin">À (si plage spécifique) :</label>
                                <input type="time" id="exc_heure_fin" name="heure_fin_exception" class="form-control <?= isset($form_errors_exception_add_page['heure_fin_exception']) ? 'input-error' : '' ?>" 
                                       value="<?= htmlspecialchars($form_data_exception_add_page['heure_fin_exception'] ?? '') ?>" step="1800">
                                <small class="form-error-message"><?= htmlspecialchars($form_errors_exception_add_page['heure_fin_exception'] ?? '') ?></small>
                            </div>
                        </div>
                        <!-- STYLE: display, margin-top inline -->
                         <small class="form-error-message error-message-display" style="display:<?= isset($form_errors_exception_add_page['time_order_exception']) ? 'block' : 'none' ?>; margin-top:0.5rem;">
                            <?= htmlspecialchars($form_errors_exception_add_page['time_order_exception'] ?? '') ?>
                         </small>

                        <!-- STYLE: margin-top inline -->
                        <div class="form-group" style="margin-top:1rem;">
                            <label for="exc_motif">Motif/Description (optionnel) :</label>
                            <input type="text" id="exc_motif" name="motif" class="form-control" value="<?= htmlspecialchars($form_data_exception_add_page['motif'] ?? '') ?>" maxlength="255" placeholder="Ex: Conférence, Congés annuels...">
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn primary-action"><i class="fas fa-plus-circle icon-left"></i>Ajouter</button>
                        </div>
                    </form>
                </div>
                <!-- STYLE: margin inline -->
                <hr style="margin: 2rem 0;">
                <!-- STYLE: font-size, margin-bottom, color inline -->
                <h3 style="font-size:1.2rem; margin-bottom:1rem; color:var(--color-primary-dark);">Mes Exceptions et Absences Programmées</h3>
                <?php if (count($exceptions_horaires_view_list) > 0): ?>
                    <div class="table-responsive-wrapper">
                        <table class="data-table">
                            <thead><tr><th>Date</th><th>Type</th><th>De</th><th>À</th><th>Motif</th><th class="actions-cell">Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($exceptions_horaires_view_list as $ex_item): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($ex_item['date_exception'])) ?></td>
                                    <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $ex_item['type_exception']))) ?></td>
                                    <td><?= $ex_item['heure_debut_formatee'] ?: 'Journée' ?></td>
                                    <td><?= $ex_item['heure_fin_formatee'] ?: 'entière' ?></td>
                                    <td><?= htmlspecialchars($ex_item['motif'] ?? '-') ?></td>
                                    <td class="actions-cell">
                                        <a href="supprimer_exception.php?id=<?= $ex_item['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Supprimer cette exception ?')" title="Supprimer"><i class="fas fa-trash-alt"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="no-slots-message text-center">Aucune exception/absence enregistrée pour les dates à venir ou récentes.</p>
                <?php endif; ?>
            </section>
        </div> 
    </div> 
</main>

<footer class="site-footer">
   <div class="container"><p class="copyright-text text-center">© <span id="footer-year"></span> SANTE TV Plus. Tous droits réservés.</p></div>
</footer>

<script src="../assets/js/script.js"></script> 
</body>
</html>