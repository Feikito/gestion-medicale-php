<?php
session_start();
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 

if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'medecin') {
    $_SESSION['flash_message_login'] = "Accès non autorisé. Veuillez vous connecter en tant que médecin.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/connexion.php'); 
    exit;
}
$medecin_id = $_SESSION['utilisateur_id'];

$stmt_medecin_info_dash = $pdo->prepare("SELECT * FROM medecins WHERE id = ?");
$stmt_medecin_info_dash->execute([$medecin_id]);
$medecin_user_data_dash = $stmt_medecin_info_dash->fetch(PDO::FETCH_ASSOC);

if (!$medecin_user_data_dash) {
    $_SESSION['flash_message_login'] = "Erreur: Profil médecin introuvable. Veuillez vous reconnecter.";
    $_SESSION['flash_type_login'] = "error";
    session_unset(); 
    session_destroy();
    header('Location: ../pages/connexion.php');
    exit;
}
$nom_medecin_display_dash = htmlspecialchars("Dr. " . ($medecin_user_data_dash['prenom'] ?? '') . ' ' . ($medecin_user_data_dash['nom'] ?? 'Médecin'));
$nom_complet_session = trim(($medecin_user_data_dash['prenom'] ?? '') . ' ' . ($medecin_user_data_dash['nom'] ?? ''));
if (($_SESSION['nom'] ?? '') !== $nom_complet_session && !empty($nom_complet_session)) {
    $_SESSION['nom'] = $nom_complet_session;
}

$compte_medecin_est_valide = ($medecin_user_data_dash['valide'] == 1);

if ($compte_medecin_est_valide) {
    try {
        $statuts_rdv_a_marquer_vus_med_dash = ['en attente', 'annulé']; 
        $check_enum_stmt_dash_med = $pdo->query("SHOW COLUMNS FROM rendez_vous LIKE 'statut'");
        $enum_definition_dash_med = $check_enum_stmt_dash_med->fetch(PDO::FETCH_ASSOC);
        if ($enum_definition_dash_med && strpos($enum_definition_dash_med['Type'], "'refusé'") !== false) {
             $statuts_rdv_a_marquer_vus_med_dash[] = 'refusé';
        }

        if (!empty($statuts_rdv_a_marquer_vus_med_dash)) {
            $in_clause_rdv_med_dash = implode(',', array_fill(0, count($statuts_rdv_a_marquer_vus_med_dash), '?'));
            $sql_mark_rdv_seen_med_dash = "UPDATE rendez_vous SET vue_par_medecin = 1 
                                     WHERE medecin_id = ? AND statut IN ($in_clause_rdv_med_dash) AND vue_par_medecin = 0 AND supprime_par_medecin = 0";
            $stmt_mark_rdv_seen_med_dash = $pdo->prepare($sql_mark_rdv_seen_med_dash);
            $params_mark_rdv_seen_med_dash = array_merge([$medecin_id], $statuts_rdv_a_marquer_vus_med_dash);
            $stmt_mark_rdv_seen_med_dash->execute($params_mark_rdv_seen_med_dash);
        }
    } catch (PDOException $e) {
        error_log("Erreur marquage RDV vus (espace_medecin) pour medecin $medecin_id: " . $e->getMessage());
    }
}

$nb_rdv_en_attente_dash = 0; 
$nb_rdv_aujourdhui_dash = 0; 
$nb_rdv_semaine_dash = 0; 
$nb_messages_non_lus_med_dash = 0;

if ($compte_medecin_est_valide) {
    try {
        $stmt_rdv_attente_dash = $pdo->prepare(
            "SELECT COUNT(*) FROM rendez_vous WHERE medecin_id = :id AND statut = 'en attente' 
             AND ( (date_rdv > CURDATE()) OR (date_rdv = CURDATE() AND heure_rdv >= CURTIME()) ) AND supprime_par_medecin = 0"
        );
        $stmt_rdv_attente_dash->execute([':id' => $medecin_id]);
        $nb_rdv_en_attente_dash = $stmt_rdv_attente_dash->fetchColumn();

        $stmt_rdv_auj_dash = $pdo->prepare(
            "SELECT COUNT(*) FROM rendez_vous WHERE medecin_id = :id AND statut = 'confirmé' AND date_rdv = CURDATE() AND supprime_par_medecin = 0"
        );
        $stmt_rdv_auj_dash->execute([':id' => $medecin_id]);
        $nb_rdv_aujourdhui_dash = $stmt_rdv_auj_dash->fetchColumn();

        $date_plus_6_jours_dash = (new DateTime('+6 days'))->format('Y-m-d');
        $stmt_rdv_semaine_dash = $pdo->prepare(
            "SELECT COUNT(*) FROM rendez_vous WHERE medecin_id = :id AND statut = 'confirmé' 
             AND date_rdv BETWEEN CURDATE() AND :date_fin AND supprime_par_medecin = 0"
        );
        $stmt_rdv_semaine_dash->execute([':id' => $medecin_id, ':date_fin' => $date_plus_6_jours_dash]);
        $nb_rdv_semaine_dash = $stmt_rdv_semaine_dash->fetchColumn();
        
        $table_messages_exists_dash = $pdo->query("SHOW TABLES LIKE 'messages'")->rowCount() > 0;
        if ($table_messages_exists_dash) {
            $check_col_stmt_dash = $pdo->query("SHOW COLUMNS FROM messages LIKE 'lu_par_medecin'");
            if ($check_col_stmt_dash->fetch()) {
                $stmt_msg_med_dash = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = :medecin_id AND lu_par_medecin = 0");
                $stmt_msg_med_dash->execute([':medecin_id' => $medecin_id]);
                $nb_messages_non_lus_med_dash = $stmt_msg_med_dash->fetchColumn(); 
            }
        }
    } catch (PDOException $e) {
        error_log("Erreur récupération stats dashboard médecin $medecin_id: " . $e->getMessage());
    }
}

$prochains_rdv_attente_dash_list = [];
if ($compte_medecin_est_valide) {
    try {
        $stmt_prochains_rdv_att_dash = $pdo->prepare("
            SELECT rv.id, rv.date_rdv, rv.heure_rdv, p.nom AS patient_nom, p.prenom AS patient_prenom
            FROM rendez_vous rv JOIN patients p ON rv.patient_id = p.id
            WHERE rv.medecin_id = :id AND rv.statut = 'en attente' 
              AND ( (rv.date_rdv > CURDATE()) OR (rv.date_rdv = CURDATE() AND rv.heure_rdv >= CURTIME()) )
              AND rv.supprime_par_medecin = 0
            ORDER BY rv.date_rdv ASC, rv.heure_rdv ASC 
            LIMIT 5
        ");
        $stmt_prochains_rdv_att_dash->execute([':id' => $medecin_id]);
        $prochains_rdv_attente_dash_list = $stmt_prochains_rdv_att_dash->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur récupération prochains RDV médecin $medecin_id: " . $e->getMessage());
    }
}

$flash_message_dash_med_page = $_SESSION['flash_message'] ?? null;
$flash_type_dash_med_page = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$csrf_token_med_dash = generate_csrf_token(); 
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Médecin - <?= $nom_medecin_display_dash ?> - SANTE TV</title>
    <meta name="description" content="Gérez vos rendez-vous, vos disponibilités et votre profil professionnel sur votre espace médecin SANTE TV.">
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 
    <script src="https://cdn.tiny.cloud/1/4amskh9mj3cii8jraizm25oi6k5kvplfgmzr48dm2b52fgj7/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body class="user-dashboard-page body-espace-medecin"> 

<header class="site-header">
    <div class="container">
        <div class="logo-branding"><a href="../index.php" title="Accueil SANTE TV"><img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img"><span class="site-title">SANTE TV</span></a></div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav"><span></span><span></span><span></span></button>
        <nav class="main-navigation" id="main-nav">
            <ul>
                <li><a href="espace_medecin.php" class="nav-link active">Mon Espace</a></li>
                <li><a href="mes_rendez_vous_medecin.php" class="nav-link">Mes Rendez-vous
                    <?php if($nb_rdv_en_attente_dash > 0 && $compte_medecin_est_valide): ?><span class="badge-notification"><?= $nb_rdv_en_attente_dash ?></span><?php endif; ?>
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

<main class="main-content section-padding">
    <div class="container">
        <div class="page-header">
            <h1 class="page-main-title">Tableau de Bord Médecin</h1>
            <p class="welcome-message">Bienvenue, <span class="user-name-placeholder"><?= $nom_medecin_display_dash ?></span> !</p>
        </div>

        <?php if ($flash_message_dash_med_page): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_dash_med_page) ?> alert-dismissible">
                <?= $flash_message_dash_med_page ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button>
            </div>
        <?php endif; ?>

        <?php if (!$compte_medecin_est_valide): ?>
            <div class="alert alert-warning" style="margin-top:1.5rem;">
                <i class="fas fa-exclamation-triangle icon-left"></i>Votre compte est actuellement <strong>en attente de validation</strong> par nos administrateurs. 
                Certaines fonctionnalités sont limitées. Vous pouvez <a href="profil_medecin.php" class="link-emphasis">compléter votre profil</a>. 
                Vous serez notifié(e) par email une fois votre compte activé.
            </div>
        <?php else: ?>
        
        <div class="dashboard-grid" style="margin-top: 2rem;">
            <div class="dashboard-card notification-card <?= ($nb_rdv_en_attente_dash > 0) ? 'has-pending' : 'info-card' ?>">
                <h3 class="card-title"><i class="fas fa-hourglass-half icon-left"></i>Demandes de RDV</h3>
                <p class="card-description">Vous avez <strong><?= $nb_rdv_en_attente_dash ?></strong> demande(s) de rendez-vous en attente de traitement.</p>
                <a href="mes_rendez_vous_medecin.php?statut=en%20attente" class="card-action-link">Gérer les demandes →</a>
            </div>
            <div class="dashboard-card info-card">
                <h3 class="card-title"><i class="fas fa-calendar-day icon-left"></i>RDV Aujourd'hui</h3>
                <p class="card-description"><strong><?= $nb_rdv_aujourdhui_dash ?></strong> rendez-vous confirmé(s) pour aujourd'hui.</p>
                <a href="mes_rendez_vous_medecin.php?periode=today" class="card-action-link">Voir le planning du jour →</a>
            </div>
             <div class="dashboard-card success-card"> 
                <h3 class="card-title"><i class="fas fa-calendar-alt icon-left"></i>RDV Confirmés (7 Prochains Jours)</h3>
                <p class="card-description"><strong><?= $nb_rdv_semaine_dash ?></strong> rendez-vous sont confirmés pour les 7 prochains jours.</p>
                <a href="mes_rendez_vous_medecin.php?periode=week" class="card-action-link">Consulter l'agenda hebdomadaire →</a>
            </div>
        </div>

        <?php if (count($prochains_rdv_attente_dash_list) > 0): ?>
        <section class="pending-appointments-section section-padding" style="padding-top:1.5rem; padding-bottom:0;">
            <div class="section-header">
                <h2 class="section-title" style="font-size:1.4rem;">Prochaines Demandes de RDV à Traiter</h2>
                <a href="mes_rendez_vous_medecin.php?statut=en%20attente" class="btn btn-sm btn-outline-primary">Voir toutes les demandes</a>
            </div>
            <div class="table-responsive-wrapper">
                <table class="data-table">
                    <thead><tr><th>Patient</th><th>Date Demandée</th><th>Heure</th><th class="actions-cell">Actions</th></tr></thead>
                    <tbody>
                        <?php foreach($prochains_rdv_attente_dash_list as $rdv_att_item): ?>
                        <tr id="rdv-dash-med-row-<?= $rdv_att_item['id'] ?>">
                            <td data-label="Patient"><?= htmlspecialchars($rdv_att_item['patient_prenom'] . ' ' . $rdv_att_item['patient_nom']) ?></td>
                            <td data-label="Date"><?= date('d/m/Y', strtotime($rdv_att_item['date_rdv'])) ?></td>
                            <td data-label="Heure"><?= date('H:i', strtotime($rdv_att_item['heure_rdv'])) ?></td>
                            <td class="actions-cell">
                                <a href="gerer_demande_rdv.php?id=<?= $rdv_att_item['id'] ?>&action=accepter&return_url=espace_medecin.php" class="btn btn-sm btn-success" title="Accepter ce RDV"><i class="fas fa-check"></i> Accepter</a>
                                <button type="button" onclick="confirmActionRdvMedecin(<?= $rdv_att_item['id'] ?>, 'refuser', 'Refuser cette demande de rendez-vous ?', true)" class="btn btn-sm btn-danger" title="Refuser ce RDV"><i class="fas fa-times"></i> Refuser</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php elseif($nb_rdv_en_attente_dash === 0): ?>
             <div class="alert alert-info text-center" style="margin-top: 2rem;">
                <i class="fas fa-check-circle icon-left"></i>Aucune nouvelle demande de rendez-vous en attente.
             </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <section class="quick-actions-section section-padding" style="padding-top:2.5rem; padding-bottom:1.5rem;">
             <h2 class="section-title text-center" style="font-size: 1.5rem; margin-bottom:1.5rem;">Ma Gestion Quotidienne</h2>
            <div class="quick-actions-grid">
                <a href="mes_rendez_vous_medecin.php" class="quick-action-item"><div class="action-icon"><i class="fas fa-calendar-alt"></i></div><div class="action-label">Tous Mes RDV</div></a>
                <a href="gestion_disponibilites_medecin.php" class="quick-action-item"><div class="action-icon"><i class="fas fa-user-clock"></i></div><div class="action-label">Mes Disponibilités</div></a>
                <a href="messages_medecin.php" class="quick-action-item"><div class="action-icon"><i class="fas fa-envelope-open-text"></i></div><div class="action-label">Messages Reçus <?php if($nb_messages_non_lus_med_dash > 0 && $compte_medecin_est_valide): ?><span class="badge-notification-inline"><?= $nb_messages_non_lus_med_dash ?></span><?php endif; ?></div></a>
                <a href="profil_medecin.php" class="quick-action-item"><div class="action-icon"><i class="fas fa-user-cog"></i></div><div class="action-label">Mon Profil Pro.</div></a>
            </div>
        </section>
    </div>
</main>

<footer class="site-footer">
   <div class="container"><p class="copyright-text text-center">© <span id="footer-year"></span> SANTE TV Plus. Tous droits réservés.</p></div>
</footer>

<script src="../assets/js/script.js"></script> 
</body>
</html>