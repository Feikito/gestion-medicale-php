<?php
session_start();
require '../php/db.php'; 

if (!isset($_SESSION['admin_id'])) { 
    $_SESSION['flash_message_login'] = "Accès refusé. Veuillez vous connecter.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/admin-login.php'); 
    exit;
}

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
     $_SESSION['flash_message'] = "ID de patient invalide ou manquant.";
     $_SESSION['flash_type'] = "error";
     header('Location: gestion_patients.php'); 
     exit;
}
$patient_id_view_admin = (int) $_GET['id'];

// S'assurer que les tables nécessaires existent
$table_patients_exists_view = $pdo->query("SHOW TABLES LIKE 'patients'")->rowCount() > 0;
$table_rdv_exists_view = $pdo->query("SHOW TABLES LIKE 'rendez_vous'")->rowCount() > 0;
$table_comments_exists_view = $pdo->query("SHOW TABLES LIKE 'commentaires'")->rowCount() > 0;
$table_medecins_exists_view = $pdo->query("SHOW TABLES LIKE 'medecins'")->rowCount() > 0;


if (!$table_patients_exists_view) {
    $_SESSION['flash_message'] = "Erreur critique: La table des patients est manquante.";
    $_SESSION['flash_type'] = "danger";
    header('Location: dashboard_admin.php');
    exit;
}

$stmt_patient_details_admin = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt_patient_details_admin->execute([$patient_id_view_admin]);
$patient_details_data_admin = $stmt_patient_details_admin->fetch(PDO::FETCH_ASSOC);

if (!$patient_details_data_admin) {
     $_SESSION['flash_message'] = "Patient non trouvé (ID: " . htmlspecialchars($patient_id_view_admin) . ").";
     $_SESSION['flash_type'] = "error";
     header('Location: gestion_patients.php');
     exit;
}

$nombre_rdv_patient_total = 0;
$nombre_rdv_patient_actifs = 0;

if ($table_rdv_exists_view) {
    try {
        $stmt_rdv_count_patient_total = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE patient_id = ?");
        $stmt_rdv_count_patient_total->execute([$patient_id_view_admin]);
        $nombre_rdv_patient_total = (int)$stmt_rdv_count_patient_total->fetchColumn();

        $stmt_rdv_count_patient_actifs = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE patient_id = ? AND statut IN ('en attente', 'confirmé') AND CONCAT(date_rdv, ' ', heure_rdv) >= NOW()");
        $stmt_rdv_count_patient_actifs->execute([$patient_id_view_admin]);
        $nombre_rdv_patient_actifs = (int)$stmt_rdv_count_patient_actifs->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur comptage RDV pour patient ID $patient_id_view_admin (voir_patient): " . $e->getMessage());
    }
}

$flash_message_view_patient_admin = $_SESSION['flash_message'] ?? null;
$flash_type_view_patient_admin = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$nb_com_att_nav_view_pat_admin = 0;
$nb_med_att_nav_view_pat_admin = 0;
if ($table_comments_exists_view) {
    try { $nb_com_att_nav_view_pat_admin = $pdo->query("SELECT COUNT(*) FROM commentaires WHERE statut = 'en attente'")->fetchColumn(); }
    catch (PDOException $e) { error_log("Erreur comptage commentaires attente (voir_patient): " . $e->getMessage()); }
}
if ($table_medecins_exists_view) {
    try { $nb_med_att_nav_view_pat_admin = $pdo->query("SELECT COUNT(*) FROM medecins WHERE valide = 0")->fetchColumn(); }
    catch (PDOException $e) { error_log("Erreur comptage médecins attente (voir_patient): " . $e->getMessage()); }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Patient - <?= htmlspecialchars($patient_details_data_admin['prenom'] . ' ' . $patient_details_data_admin['nom']) ?> - Admin SANTE TV</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="admin-view-details-page">

<header class="site-header admin-header">
    <div class="container">
        <div class="logo-branding"><a href="dashboard_admin.php" title="Tableau de Bord Admin"><img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img"><span class="site-title">Admin SANTE TV</span></a></div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav"><span></span><span></span><span></span></button>
        <nav class="main-navigation admin-navigation" id="main-nav">
             <ul>
                <li><a href="dashboard_admin.php" class="nav-link">Dashboard</a></li>
                <li><a href="gestion_medecins.php" class="nav-link">Médecins <?php if($nb_med_att_nav_view_pat_admin > 0): ?><span class="badge-notification"><?= $nb_med_att_nav_view_pat_admin ?></span><?php endif; ?></a></li>
                <li><a href="gestion_patients.php" class="nav-link active">Patients</a></li> 
                <li><a href="gestion_rdv.php" class="nav-link">Rendez-vous</a></li>
                <li><a href="gestion_commentaires.php" class="nav-link">Commentaires <?php if($nb_com_att_nav_view_pat_admin > 0): ?><span class="badge-notification"><?= $nb_com_att_nav_view_pat_admin ?></span><?php endif; ?></a></li>
                <li><a href="deconnexion_admin.php" class="nav-link btn btn-sm btn-danger">Déconnexion</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content admin-page-container section-padding"> 
    <div class="container">
        <div class="page-header">
            <h1 class="page-main-title">
                Profil du Patient : <?= htmlspecialchars($patient_details_data_admin['prenom'] . ' ' . $patient_details_data_admin['nom']) ?>
            </h1>
            <a href="gestion_patients.php<?= !empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'gestion_patients.php') !== false ? '?'.parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY) : '' ?>" 
               class="btn btn-sm secondary-action">← Retour à la liste des patients</a>
        </div>

        <?php if ($flash_message_view_patient_admin): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_view_patient_admin) ?> alert-dismissible">
                <?= htmlspecialchars($flash_message_view_patient_admin) ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button> 
            </div>
        <?php endif; ?>

        <div class="profile-view-card card"> 
             <!-- STYLE: margin-bottom inline -->
            <div class="text-center" style="margin-bottom: 1.5rem;">
                <img src="<?= $patient_details_data_admin['photo'] ? '../' . htmlspecialchars($patient_details_data_admin['photo']) : '../assets/images/placeholder-patient.png' ?>" 
                     alt="Photo de <?= htmlspecialchars($patient_details_data_admin['prenom'] . ' ' . $patient_details_data_admin['nom']) ?>" class="profile-photo">
            </div>

            <dl class="profile-details-grid">
                <dt><i class="fas fa-user icon-left"></i>ID Patient :</dt>
                <dd><?= htmlspecialchars($patient_details_data_admin['id']) ?></dd>

                <dt><i class="fas fa-signature icon-left"></i>Nom Complet :</dt>
                <dd><?= htmlspecialchars($patient_details_data_admin['prenom'] . ' ' . $patient_details_data_admin['nom']) ?></dd>
                
                <dt><i class="fas fa-envelope icon-left"></i>Email :</dt>
                <dd><a href="mailto:<?= htmlspecialchars($patient_details_data_admin['email']) ?>"><?= htmlspecialchars($patient_details_data_admin['email']) ?></a></dd>

                <dt><i class="fas fa-map-marker-alt icon-left"></i>Adresse :</dt>
                <dd><?= htmlspecialchars($patient_details_data_admin['adresse'] ?? 'Non fournie') ?></dd>
                
                <dt><i class="fas fa-birthday-cake icon-left"></i>Date de Naissance :</dt>
                <dd><?= $patient_details_data_admin['date_naissance'] ? date('d/m/Y', strtotime($patient_details_data_admin['date_naissance'])) : '<span class="text-muted">Non fournie</span>' ?></dd>
                
                <dt><i class="fas fa-venus-mars icon-left"></i>Sexe :</dt>
                <dd><?= htmlspecialchars($patient_details_data_admin['sexe'] ?? 'Non fourni') ?></dd>

                <?php 
                $patient_cols_view = array_keys($patient_details_data_admin);
                if (in_array('telephone', $patient_cols_view) && !empty($patient_details_data_admin['telephone'])): 
                ?>
                <dt><i class="fas fa-phone icon-left"></i>Téléphone :</dt>
                <dd><?= htmlspecialchars($patient_details_data_admin['telephone']) ?></dd>
                <?php endif; ?>

                <dt><i class="fas fa-calendar-check icon-left"></i>RDV Actifs :</dt>
                <dd><?= $nombre_rdv_patient_actifs ?>
                    <?php if($nombre_rdv_patient_actifs > 0): ?>
                         <!-- STYLE: margin-left inline -->
                        <a href="gestion_rdv.php?patient=<?= $patient_id_view_admin ?>&status=actifs" title="Voir les RDV actifs de ce patient" class="link-icon" style="margin-left:10px;"><i class="fas fa-list-ul"></i></a>
                    <?php endif; ?>
                </dd>
                
                <dt><i class="fas fa-history icon-left"></i>Total RDV Historique :</dt>
                <dd><?= $nombre_rdv_patient_total ?>
                    <?php if($nombre_rdv_patient_total > 0): ?>
                        <!-- STYLE: margin-left inline -->
                        <a href="gestion_rdv.php?patient=<?= $patient_id_view_admin ?>" title="Voir tous les RDV de ce patient" class="link-icon" style="margin-left:10px;"><i class="fas fa-list-ul"></i></a>
                    <?php endif; ?>
                </dd>
                
                <?php if(isset($patient_details_data_admin['date_inscription']) && $patient_details_data_admin['date_inscription']): ?>
                 <dt><i class="fas fa-user-plus icon-left"></i>Date d'Inscription :</dt>
                 <dd><?= date('d/m/Y H:i', strtotime($patient_details_data_admin['date_inscription'])) ?></dd>
                <?php endif; ?>
            </dl>

            <!-- STYLE: margin inline -->
            <hr style="margin: 2rem 0;">

            <div class="actions-toolbar text-center">
                 <!-- Le return_url pour la suppression doit pointer vers la liste des patients, pas cette page qui n'existera plus -->
                 <a href="supprimer_patient.php?id=<?= $patient_details_data_admin['id'] ?>&return_url=<?= urlencode('gestion_patients.php' . (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'gestion_patients.php') !== false ? '?'.parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY) : '')) ?>" 
                    class="btn btn-danger" 
                    onclick="return confirm('ATTENTION : La suppression de ce patient entraînera la suppression irréversible de tous ses rendez-vous et messages associés. Êtes-vous absolument sûr ?')">
                    <i class="fas fa-user-times icon-left"></i> Supprimer ce Patient
                </a>
            </div>
        </div> 
    </div> 
</main>

<footer class="site-footer admin-footer">
   <div class="container"><p class="copyright-text text-center">© <span id="footer-year"></span> SANTE TV - Espace Administration.</p></div>
</footer>

<script src="../assets/js/script.js"></script> 
</body>
</html>