<?php
session_start(); 
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 

if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'patient') {
    $_SESSION['flash_message_login'] = "Accès non autorisé. Veuillez vous connecter.";
    $_SESSION['flash_type_login'] = "warning";
    header('Location: ../pages/connexion.php'); 
    exit;
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) { 
    session_unset();
    session_destroy();
    $_SESSION['flash_message_login'] = "Votre session a expiré pour inactivité. Veuillez vous reconnecter.";
    $_SESSION['flash_type_login'] = "info";
    header('Location: ../pages/connexion.php');
    exit;
}
$_SESSION['last_activity'] = time(); 

$patient_id = $_SESSION['utilisateur_id'];
$nom_patient_session = $_SESSION['nom'] ?? 'Patient'; 
$compte_patient_actif = true; 

$stmt_patient_info = $pdo->prepare("SELECT nom, prenom FROM patients WHERE id = ?");
$stmt_patient_info->execute([$patient_id]);
$patient_db_data = $stmt_patient_info->fetch(PDO::FETCH_ASSOC);
$nom_patient_display = $patient_db_data ? htmlspecialchars($patient_db_data['prenom'] . ' ' . $patient_db_data['nom']) : htmlspecialchars($nom_patient_session);
if ($patient_db_data && ($_SESSION['nom'] !== ($patient_db_data['prenom'] . ' ' . $patient_db_data['nom']))) {
    $_SESSION['nom'] = $patient_db_data['prenom'] . ' ' . $patient_db_data['nom'];
}

try {
    $statuts_a_marquer_vus = ['confirmé', 'annulé'];
    $check_enum_stmt = $pdo->query("SHOW COLUMNS FROM rendez_vous LIKE 'statut'");
    $enum_definition = $check_enum_stmt->fetch(PDO::FETCH_ASSOC);
    if ($enum_definition && strpos($enum_definition['Type'], "'refusé'") !== false) {
        $statuts_a_marquer_vus[] = 'refusé';
    }
    if (!empty($statuts_a_marquer_vus)) {
        $in_clause_placeholders = implode(',', array_fill(0, count($statuts_a_marquer_vus), '?'));
        $sql_mark_seen = "UPDATE rendez_vous SET vue_par_patient = 1 
                          WHERE patient_id = ? AND statut IN ($in_clause_placeholders) AND vue_par_patient = 0 AND supprime_par_patient = 0";
        $stmt_mark_seen = $pdo->prepare($sql_mark_seen);
        $params_mark_seen = array_merge([$patient_id], $statuts_a_marquer_vus);
        $stmt_mark_seen->execute($params_mark_seen);
    }
} catch (PDOException $e) {
    error_log("Erreur marquage RDV vus (dashboard_patient) pour patient $patient_id: " . $e->getMessage());
}

$stmt_rdv_actifs = $pdo->prepare("
    SELECT COUNT(*) FROM rendez_vous WHERE patient_id = :patient_id 
    AND ( (date_rdv > CURDATE()) OR (date_rdv = CURDATE() AND heure_rdv >= CURTIME()) )
    AND statut IN ('en attente', 'confirmé') AND supprime_par_patient = 0
");
$stmt_rdv_actifs->execute([':patient_id' => $patient_id]);
$nombre_rendezvous_actifs = $stmt_rdv_actifs->fetchColumn();

$nombre_notifications_non_lues = 0;
try {
    $table_exists_stmt = $pdo->query("SHOW TABLES LIKE 'notifications_patients'");
    if ($table_exists_stmt->rowCount() > 0) {
        $stmt_notif_non_lues = $pdo->prepare("SELECT COUNT(*) FROM notifications_patients WHERE patient_id = :patient_id AND lu = 0");
        $stmt_notif_non_lues->execute([':patient_id' => $patient_id]);
        $nombre_notifications_non_lues = $stmt_notif_non_lues->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Erreur comptage notifications_patients: " . $e->getMessage());
}

$medecins_valides_form = [];
try {
    $stmt_medecins = $pdo->query("SELECT id, nom, prenom, specialite FROM medecins WHERE valide = 1 ORDER BY nom, prenom");
    $medecins_valides_form = $stmt_medecins->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur récupération médecins valides (dashboard_patient): " . $e->getMessage());
}

$flash_message_dashboard = $_SESSION['flash_message'] ?? null;
$flash_type_dashboard = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']); 

$csrf_token_dashboard_msg = generate_csrf_token(); 

$form_data_message_patient = $_SESSION['form_data_message_patient_dashboard'] ?? [];
$form_errors_message_patient = $_SESSION['form_errors_message_patient_dashboard'] ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Espace Patient - <?= $nom_patient_display ?> - SANTE TV</title>
    <meta name="description" content="Accédez à votre espace patient SANTE TV pour gérer vos rendez-vous médicaux, consulter vos notifications et mettre à jour votre profil.">
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">       
    <script src="https://cdn.tiny.cloud/1/4amskh9mj3cii8jraizm25oi6k5kvplfgmzr48dm2b52fgj7/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

</head>
<body class="user-dashboard-page"> 

<header class="site-header">
    <div class="container">
        <div class="logo-branding">
            <a href="../index.php" title="SANTE TV Accueil"> 
                <img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img">
                <span class="site-title">SANTE TV</span>
            </a>
        </div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav">
            <span></span><span></span><span></span>
        </button>
        <nav class="main-navigation" id="main-nav">
            <ul>
                <li><a href="dashboard_patient.php" class="nav-link active">Mon Espace</a></li>
                <li><a href="../pages/docteurs.php" class="nav-link">Trouver un Médecin</a></li>
                <li><a href="mes_rendez_vous_patient.php" class="nav-link">Mes Rendez-vous
                    <?php if($nombre_rendezvous_actifs > 0): ?>
                        <span class="badge-notification"><?= $nombre_rendezvous_actifs ?></span>
                    <?php endif; ?>
                </a></li>
                <li><a href="profil_patient.php" class="nav-link">Mon Profil</a></li>
                <li><a href="messages_patient.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'messages_patient.php') ? 'active' : ''; ?>"> Notifications
                    <?php if(isset($nombre_notifications_non_lues) && $nombre_notifications_non_lues > 0): // Adaptez le nom de la variable si besoin ?>
                    <span class="badge-notification"><?= $nombre_notifications_non_lues ?></span>
                    <?php endif; ?>
                    </a>
                </li>
                <li><a href="deconnexion.php" class="nav-link btn btn-sm btn-danger">Déconnexion</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content section-padding"> 
    <div class="container">
        <div class="page-header">
            <h1 class="page-main-title">Bienvenue sur votre Espace, <span class="user-name-placeholder"><?= $nom_patient_display ?></span> !</h1>
            <p class="section-subtitle" style="margin-bottom: 0;">Gérez vos rendez-vous médicaux et communiquez facilement avec les professionnels de santé.</p>
        </div>

        <?php if ($flash_message_dashboard): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_dashboard) ?> alert-dismissible" style="margin-top: 1.5rem;">
                <?= $flash_message_dashboard ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button>
            </div>
        <?php endif; ?>

        <div class="dashboard-grid" style="margin-top: 2rem;">
            <div class="dashboard-card notification-card <?= ($nombre_rendezvous_actifs > 0) ? 'has-pending' : 'info-card' ?>">
                <h3 class="card-title"><i class="fas fa-calendar-check icon-left"></i>Rendez-vous en Cours</h3>
                <p class="card-description">
                    Vous avez <strong><?= htmlspecialchars($nombre_rendezvous_actifs) ?></strong> rendez-vous
                    <?= ($nombre_rendezvous_actifs > 0) ? 'programmés (en attente de confirmation ou confirmés).' : 'actifs.' ?>
                </p>
                <a href="mes_rendez_vous_patient.php" class="card-action-link">Consulter mes rendez-vous →</a>
            </div>

            <div class="dashboard-card action-card info-card"> 
                <h3 class="card-title"><i class="fas fa-calendar-plus icon-left"></i>Prendre un Nouveau RDV</h3>
                <p class="card-description">Trouvez un spécialiste et réservez votre prochain créneau en quelques clics.</p>
                <a href="../pages/docteurs.php" class="btn primary-action btn-block">
                    <i class="fas fa-search-plus icon-left"></i>Rechercher un Médecin
                </a>
            </div>

            <?php if (count($medecins_valides_form) > 0 && $compte_patient_actif): ?>
            <div class="dashboard-card message-card"> 
                <h3 class="card-title"><i class="fas fa-envelope icon-left"></i>Contacter un Médecin</h3>
                <?php if (isset($form_errors_message_patient['_general'])): ?>
                    <p class="alert alert-danger" style="font-size:0.85em; padding:0.5em; margin-bottom:0.5em;"><?= htmlspecialchars($form_errors_message_patient['_general']) ?></p>
                <?php endif; ?>
                <form action="envoyer_message_patient.php" method="POST" class="user-form compact-form" id="formEnvoyerMessagePatientDashboard">
                     <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_dashboard_msg) ?>">
                    <input type="hidden" name="form_origin_message" value="dashboard_patient.php"> 
                    
                    <div class="form-group">
                        <label for="medecin_id_msg_dash">Destinataire : <span class="text-danger">*</span></label>
                        <select name="medecin_id" id="medecin_id_msg_dash" class="form-control <?= isset($form_errors_message_patient['medecin_id']) ? 'input-error' : '' ?>" required>
                            <option value="">-- Choisissez un médecin --</option>
                            <?php foreach ($medecins_valides_form as $medecin): ?>
                                <option value="<?= htmlspecialchars($medecin['id']) ?>" 
                                        <?= (($form_data_message_patient['medecin_id'] ?? '') == $medecin['id']) ? 'selected' : '' ?>>
                                    Dr. <?= htmlspecialchars($medecin['prenom'] . ' ' . $medecin['nom'] . ' (' . $medecin['specialite'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-error-message"><?= htmlspecialchars($form_errors_message_patient['medecin_id'] ?? '') ?></small>
                    </div>
                     <div class="form-group">
                        <label for="sujet_msg_dash">Sujet (Optionnel) :</label>
                        <input type="text" name="sujet" id="sujet_msg_dash" class="form-control <?= isset($form_errors_message_patient['sujet']) ? 'input-error' : '' ?>" 
                               value="<?= htmlspecialchars($form_data_message_patient['sujet'] ?? '') ?>" 
                               placeholder="Ex: Question sur mon RDV du JJ/MM">
                        <small class="form-error-message"><?= htmlspecialchars($form_errors_message_patient['sujet'] ?? '') ?></small>
                    </div>
                    <div class="form-group">
                        <label for="contenu_msg_dash">Votre message : <span class="text-danger">*</span></label>
                        <textarea name="contenu" id="contenu_msg_dash" rows="4" class="form-control <?= isset($form_errors_message_patient['contenu']) ? 'input-error' : '' ?>" required placeholder="Écrivez votre message ici..."><?= htmlspecialchars($form_data_message_patient['contenu'] ?? '') ?></textarea>
                        <small class="form-error-message"><?= htmlspecialchars($form_errors_message_patient['contenu'] ?? '') ?></small>
                    </div>
                    <button type="submit" class="btn primary-action btn-block">
                        <i class="fas fa-paper-plane icon-left"></i>Envoyer le Message
                    </button>
                </form>
                <?php 
                unset($_SESSION['form_data_message_patient_dashboard']);
                unset($_SESSION['form_errors_message_patient_dashboard']);
                ?>
            </div>
            <?php elseif(!$compte_patient_actif): ?>
                 <div class="dashboard-card info-card">
                    <h3 class="card-title"><i class="fas fa-envelope icon-left"></i>Contacter un Médecin</h3>
                    <p class="card-description">Votre compte doit être actif pour contacter les médecins.</p>
                </div>
            <?php else: ?>
                 <div class="dashboard-card info-card">
                    <h3 class="card-title"><i class="fas fa-envelope icon-left"></i>Contacter un Médecin</h3>
                    <p class="card-description">Aucun médecin n'est actuellement disponible pour la messagerie.</p>
                </div>
            <?php endif; ?>
        </div> 

        <section class="quick-actions-section section-padding" style="padding-top:2.5rem; padding-bottom:1.5rem;">
             <h2 class="section-title text-center" style="font-size: 1.5rem; margin-bottom:1.5rem;">Accès Rapides à Vos Services</h2>
            <div class="quick-actions-grid">
                <a href="mes_rendez_vous_patient.php" class="quick-action-item">
                    <div class="action-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="action-label">Mes Rendez-vous</div>
                </a>
                <a href="messages_patient.php" class="quick-action-item">
                    <div class="action-icon"><i class="fas fa-bell"></i></div> 
                    <div class="action-label">Mes Notifications
                         <?php if($nombre_notifications_non_lues > 0): ?>
                            <span class="badge-notification-inline"><?= $nombre_notifications_non_lues ?></span>
                        <?php endif; ?>
                    </div>
                </a>
                <a href="profil_patient.php" class="quick-action-item">
                    <div class="action-icon"><i class="fas fa-user-edit"></i></div>
                    <div class="action-label">Modifier Mon Profil</div>
                </a>
                <a href="../pages/docteurs.php" class="quick-action-item">
                    <div class="action-icon"><i class="fas fa-user-md"></i></div> 
                    <div class="action-label">Trouver un Médecin</div>
                </a>
            </div>
        </section>
    </div> 
</main>

<footer class="site-footer">
   <div class="container">
        <p class="copyright-text text-center">© <span id="footer-year"></span> SANTE TV Plus. Tous droits réservés.</p>
    </div>
</footer>

<script src="../assets/js/script.js"></script> 
</body>
</html>