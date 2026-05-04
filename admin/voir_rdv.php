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
     $_SESSION['flash_message'] = "ID de rendez-vous invalide ou manquant.";
     $_SESSION['flash_type'] = "error";
     header('Location: gestion_rdv.php'); 
     exit;
}
$id_rdv_view_admin = (int) $_GET['id'];

// S'assurer que les tables nécessaires existent
$tables_to_check_voir_rdv = ['rendez_vous', 'medecins', 'patients', 'commentaires'];
$existing_tables_voir_rdv = [];
foreach ($tables_to_check_voir_rdv as $table_voir_rdv) {
    if ($pdo->query("SHOW TABLES LIKE '$table_voir_rdv'")->rowCount() > 0) {
        $existing_tables_voir_rdv[$table_voir_rdv] = true;
    } else {
        error_log("Table '$table_voir_rdv' non trouvée pour la page admin/voir_rdv.php.");
        if ($table_voir_rdv === 'rendez_vous' || $table_voir_rdv === 'medecins' || $table_voir_rdv === 'patients') {
            $_SESSION['flash_message'] = "Erreur critique: Une table de données essentielle est manquante.";
            $_SESSION['flash_type'] = "danger";
            header('Location: dashboard_admin.php');
            exit;
        }
    }
}


// Récupération des infos du RDV
$sql_rdv_details = "SELECT 
                        rv.id, rv.date_rdv, rv.heure_rdv, rv.statut, rv.motif_rdv, rv.motif_annulation, 
                        rv.vue_par_patient, rv.vue_par_medecin, ";
// Vérifier si la colonne created_at existe
$colonnes_rdv = $pdo->query("DESCRIBE rendez_vous")->fetchAll(PDO::FETCH_COLUMN);
$has_created_at_rdv = in_array('created_at', $colonnes_rdv);
if ($has_created_at_rdv) {
    $sql_rdv_details .= "rv.created_at AS date_creation_rdv, ";
} else {
    $sql_rdv_details .= "NULL AS date_creation_rdv, "; // Fallback si la colonne n'existe pas
}
$sql_rdv_details .= " p.id AS patient_id_val, p.nom AS patient_nom, p.prenom AS patient_prenom, p.email AS patient_email,
                      m.id AS medecin_id_val, m.nom AS medecin_nom, m.prenom AS medecin_prenom, m.email AS medecin_email, m.specialite AS medecin_specialite
                    FROM rendez_vous rv
                    JOIN patients p ON rv.patient_id = p.id
                    JOIN medecins m ON rv.medecin_id = m.id
                    WHERE rv.id = ?";

$stmt_rdv_details_admin = $pdo->prepare($sql_rdv_details);
$stmt_rdv_details_admin->execute([$id_rdv_view_admin]);
$rdv_details_data_admin = $stmt_rdv_details_admin->fetch(PDO::FETCH_ASSOC);

if (!$rdv_details_data_admin) {
     $_SESSION['flash_message'] = "Rendez-vous non trouvé (ID: " . htmlspecialchars($id_rdv_view_admin) . ").";
     $_SESSION['flash_type'] = "error";
     header('Location: gestion_rdv.php');
     exit;
}

$flash_message_view_rdv_admin = $_SESSION['flash_message'] ?? null;
$flash_type_view_rdv_admin = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$nb_com_att_nav_view_rdv_admin = 0;
$nb_med_att_nav_view_rdv_admin = 0;
if (isset($existing_tables_voir_rdv['commentaires'])) {
    try { $nb_com_att_nav_view_rdv_admin = $pdo->query("SELECT COUNT(*) FROM commentaires WHERE statut = 'en attente'")->fetchColumn(); }
    catch (PDOException $e) { error_log("Erreur comptage commentaires (voir_rdv): " . $e->getMessage());}
}
if (isset($existing_tables_voir_rdv['medecins'])) {
    try { $nb_med_att_nav_view_rdv_admin = $pdo->query("SELECT COUNT(*) FROM medecins WHERE valide = 0")->fetchColumn(); }
    catch (PDOException $e) { error_log("Erreur comptage médecins (voir_rdv): " . $e->getMessage());}
}


$date_creation_rdv_display = 'N/A';
if (isset($rdv_details_data_admin['date_creation_rdv']) && !empty($rdv_details_data_admin['date_creation_rdv'])) { 
    try {
        $dt_creation = new DateTime($rdv_details_data_admin['date_creation_rdv']);
        $date_creation_rdv_display = $dt_creation->format('d/m/Y à H:i:s');
    } catch (Exception $e) { /* Rester N/A */ }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Rendez-vous #<?= htmlspecialchars($rdv_details_data_admin['id']) ?> - Admin SANTE TV</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- STYLE: Déplacer ce style vers styles.css -->
    <style>
        .admin-view-details-page .details-section { margin-bottom: 2rem; }
        .admin-view-details-page .details-section h3 {
            font-size: 1.2rem;
            color: var(--color-primary-dark);
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--color-brand-blue);
            display: inline-block; 
        }
        .admin-view-details-page .details-section h3 .icon-left { margin-right: 0.6rem; color: var(--color-brand-blue); }
    </style>
</head>
<body class="admin-view-details-page">

<header class="site-header admin-header">
    <div class="container">
        <div class="logo-branding"><a href="dashboard_admin.php" title="Tableau de Bord Admin"><img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img"><span class="site-title">Admin SANTE TV</span></a></div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav"><span></span><span></span><span></span></button>
        <nav class="main-navigation admin-navigation" id="main-nav">
             <ul>
                <li><a href="dashboard_admin.php" class="nav-link">Dashboard</a></li>
                <li><a href="gestion_medecins.php" class="nav-link">Médecins <?php if($nb_med_att_nav_view_rdv_admin > 0): ?><span class="badge-notification"><?= $nb_med_att_nav_view_rdv_admin ?></span><?php endif; ?></a></li>
                <li><a href="gestion_patients.php" class="nav-link">Patients</a></li>
                <li><a href="gestion_rdv.php" class="nav-link active">Rendez-vous</a></li> 
                <li><a href="gestion_commentaires.php" class="nav-link">Commentaires <?php if($nb_com_att_nav_view_rdv_admin > 0): ?><span class="badge-notification"><?= $nb_com_att_nav_view_rdv_admin ?></span><?php endif; ?></a></li>
                <li><a href="deconnexion_admin.php" class="nav-link btn btn-sm btn-danger">Déconnexion</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content admin-page-container section-padding"> 
    <div class="container">
        <div class="page-header">
            <h1 class="page-main-title">
                Détails du Rendez-vous #<?= htmlspecialchars($rdv_details_data_admin['id']) ?>
                <?php 
                    $statut_rdv_admin_view = strtolower($rdv_details_data_admin['statut']);
                    $statut_class_rdv_admin_view = 'status-badge statut-' . str_replace(' ', '-', $statut_rdv_admin_view);
                    
                    $enum_values_rdv_statut = [];
                    if (isset($existing_tables_voir_rdv['rendez_vous'])) {
                        $check_enum_stmt_view_rdv = $pdo->query("SHOW COLUMNS FROM rendez_vous LIKE 'statut'");
                        $enum_definition_view_rdv = $check_enum_stmt_view_rdv->fetch(PDO::FETCH_ASSOC);
                        if ($enum_definition_view_rdv && preg_match_all("/'([^']+)'/", $enum_definition_view_rdv['Type'], $matches_enum_view_rdv)) {
                            $enum_values_rdv_statut = $matches_enum_view_rdv[1];
                        }
                    }
                    if ($statut_rdv_admin_view === 'refusé' && !in_array('refusé', $enum_values_rdv_statut) ) {
                        $statut_class_rdv_admin_view = 'status-badge statut-annulé'; 
                    }
                    echo '<span class="' . htmlspecialchars($statut_class_rdv_admin_view) . '">' . htmlspecialchars(ucfirst($rdv_details_data_admin['statut'])) . '</span>';
                ?>
            </h1>
            <a href="gestion_rdv.php<?= !empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'gestion_rdv.php') !== false && strpos(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH), 'gestion_rdv.php') !== false ? '?'.parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY) : '' ?>" 
               class="btn btn-sm secondary-action">← Retour à la liste des RDV</a>
        </div>

        <?php if ($flash_message_view_rdv_admin): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_view_rdv_admin) ?> alert-dismissible">
                <?= htmlspecialchars($flash_message_view_rdv_admin) ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer" onclick="this.parentElement.style.display='none';">×</button> 
            </div>
        <?php endif; ?>

        <div class="profile-view-card card">
            
            <div class="details-section">
                <h3><i class="fas fa-calendar-alt icon-left"></i>Informations du Rendez-vous</h3>
                <dl class="profile-details-grid">
                    <dt>ID Rendez-vous :</dt>
                    <dd>#<?= htmlspecialchars($rdv_details_data_admin['id']) ?></dd>

                    <dt>Date Demandée :</dt>
                    <dd><?= date('d/m/Y', strtotime($rdv_details_data_admin['date_rdv'])) ?></dd>

                    <dt>Heure Demandée :</dt>
                    <dd><?= date('H:i', strtotime($rdv_details_data_admin['heure_rdv'])) ?></dd>
                    
                    <?php if (!empty($rdv_details_data_admin['motif_rdv'])): ?>
                    <dt>Motif de Consultation :</dt>
                    <dd><?= nl2br(htmlspecialchars($rdv_details_data_admin['motif_rdv'])) ?></dd>
                    <?php endif; ?>

                    <dt>Statut Actuel :</dt>
                    <!-- STYLE: font-weight inline -->
                    <dd class="<?= htmlspecialchars(str_replace('status-badge ', '', $statut_class_rdv_admin_view)) ?>" style="font-weight:bold;"><?= htmlspecialchars(ucfirst($rdv_details_data_admin['statut'])) ?></dd>

                    <?php if (in_array(strtolower($rdv_details_data_admin['statut']), ['annulé', 'refusé']) && !empty($rdv_details_data_admin['motif_annulation'])): ?>
                    <dt>Motif d'Annulation/Refus :</dt>
                    <dd><?= nl2br(htmlspecialchars($rdv_details_data_admin['motif_annulation'])) ?></dd>
                    <?php endif; ?>

                    <?php if ($has_created_at_rdv): // Afficher seulement si la colonne existe ?>
                    <dt>Date de Création RDV :</dt>
                    <dd><?= $date_creation_rdv_display ?></dd>
                    <?php endif; ?>

                    <dt>Vu par Patient :</dt>
                    <!-- STYLE: color inline -->
                    <dd><?= $rdv_details_data_admin['vue_par_patient'] ? '<i class="fas fa-eye" style="color:var(--color-success);" title="Notification lue"></i> Oui' : '<i class="fas fa-eye-slash" style="color:var(--text-color-muted);" title="Notification non lue"></i> Non' ?></dd>
                    
                    <dt>Vu par Médecin :</dt>
                    <!-- STYLE: color inline -->
                    <dd><?= $rdv_details_data_admin['vue_par_medecin'] ? '<i class="fas fa-eye" style="color:var(--color-success);" title="Notification lue"></i> Oui' : '<i class="fas fa-eye-slash" style="color:var(--text-color-muted);" title="Notification non lue"></i> Non' ?></dd>
                </dl>
            </div>

            <div class="details-section">
                <h3><i class="fas fa-user icon-left"></i>Informations du Patient</h3>
                <dl class="profile-details-grid">
                    <dt>Nom Complet :</dt>
                    <dd><a href="voir_patient.php?id=<?= $rdv_details_data_admin['patient_id_val'] ?>" title="Voir le profil du patient"><?= htmlspecialchars($rdv_details_data_admin['patient_prenom'] . ' ' . $rdv_details_data_admin['patient_nom']) ?></a></dd>
                    
                    <dt>Email Patient :</dt>
                    <dd><a href="mailto:<?= htmlspecialchars($rdv_details_data_admin['patient_email']) ?>"><?= htmlspecialchars($rdv_details_data_admin['patient_email']) ?></a></dd>
                </dl>
            </div>

            <div class="details-section">
                <h3><i class="fas fa-user-md icon-left"></i>Informations du Médecin</h3>
                <dl class="profile-details-grid">
                    <dt>Nom Complet :</dt>
                    <dd><a href="voir_medecin.php?id=<?= $rdv_details_data_admin['medecin_id_val'] ?>" title="Voir le profil du médecin">Dr. <?= htmlspecialchars($rdv_details_data_admin['medecin_prenom'] . ' ' . $rdv_details_data_admin['medecin_nom']) ?></a></dd>
                    
                    <dt>Spécialité :</dt>
                    <dd><?= htmlspecialchars($rdv_details_data_admin['medecin_specialite']) ?></dd>

                    <dt>Email Médecin :</dt>
                    <dd><a href="mailto:<?= htmlspecialchars($rdv_details_data_admin['medecin_email']) ?>"><?= htmlspecialchars($rdv_details_data_admin['medecin_email']) ?></a></dd>
                </dl>
            </div>
            
            <!-- STYLE: margin inline -->
            <hr style="margin: 2rem 0;">

            <div class="actions-toolbar text-center">
                <!-- CORRECTION: return_url pour la suppression doit pointer vers la liste gestion_rdv.php -->
                <a href="supprimer_rdv.php?id=<?= $rdv_details_data_admin['id'] ?>&return_url=<?= urlencode('gestion_rdv.php' . (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'gestion_rdv.php') !== false && strpos(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH), 'gestion_rdv.php') !== false ? '?'.parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY) : '')) ?>" 
                   class="btn btn-danger" 
                   onclick="return confirm('Supprimer définitivement ce rendez-vous ?')">
                   <i class="fas fa-trash icon-left"></i> Supprimer ce RDV
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