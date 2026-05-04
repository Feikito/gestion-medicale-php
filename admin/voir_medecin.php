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
     $_SESSION['flash_message'] = "ID de médecin invalide ou manquant.";
     $_SESSION['flash_type'] = "error";
     header('Location: gestion_medecins.php'); 
     exit;
}
$medecin_id_view = (int) $_GET['id'];

// S'assurer que la table medecins existe
$table_medecins_exists_view = $pdo->query("SHOW TABLES LIKE 'medecins'")->rowCount() > 0;
if (!$table_medecins_exists_view) {
    $_SESSION['flash_message'] = "Erreur critique: La table des médecins est manquante.";
    $_SESSION['flash_type'] = "danger";
    header('Location: dashboard_admin.php'); // Rediriger vers le dashboard si la table principale manque
    exit;
}

$stmt_medecin_details = $pdo->prepare("SELECT * FROM medecins WHERE id = ?");
$stmt_medecin_details->execute([$medecin_id_view]);
$medecin_details_data = $stmt_medecin_details->fetch(PDO::FETCH_ASSOC);

if (!$medecin_details_data) {
     $_SESSION['flash_message'] = "Médecin non trouvé (ID: " . htmlspecialchars($medecin_id_view) . ").";
     $_SESSION['flash_type'] = "error";
     header('Location: gestion_medecins.php');
     exit;
}

$nombre_rdv_medecin = 0;
if ($pdo->query("SHOW TABLES LIKE 'rendez_vous'")->rowCount() > 0) {
    try {
        $stmt_rdv_count_med = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE medecin_id = ?");
        $stmt_rdv_count_med->execute([$medecin_id_view]);
        $nombre_rdv_medecin = (int)$stmt_rdv_count_med->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur comptage RDV pour médecin ID $medecin_id_view (voir_medecin): " . $e->getMessage());
    }
}

$flash_message_view_med = $_SESSION['flash_message'] ?? null;
$flash_type_view_med = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$nb_com_att_nav_view_med = 0;
if ($pdo->query("SHOW TABLES LIKE 'commentaires'")->rowCount() > 0) {
    try {
        $nb_com_att_nav_view_med = $pdo->query("SELECT COUNT(*) FROM commentaires WHERE statut = 'en attente'")->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur comptage commentaires en attente (voir_medecin): " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Médecin - Dr. <?= htmlspecialchars($medecin_details_data['prenom'] . ' ' . $medecin_details_data['nom']) ?> - Admin SANTE TV</title>
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
                <li><a href="gestion_medecins.php" class="nav-link active">Médecins</a></li> 
                <li><a href="gestion_patients.php" class="nav-link">Patients</a></li>
                <li><a href="gestion_rdv.php" class="nav-link">Rendez-vous</a></li>
                <li><a href="gestion_commentaires.php" class="nav-link">Commentaires <?php if($nb_com_att_nav_view_med > 0): ?><span class="badge-notification"><?= $nb_com_att_nav_view_med ?></span><?php endif; ?></a></li>
                <li><a href="deconnexion_admin.php" class="nav-link btn btn-sm btn-danger">Déconnexion</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content admin-page-container section-padding"> 
    <div class="container">
        <div class="page-header">
            <h1 class="page-main-title">
                Profil du Médecin : Dr. <?= htmlspecialchars($medecin_details_data['prenom'] . ' ' . $medecin_details_data['nom']) ?>
                 <?php 
                    if ($medecin_details_data['valide'] == 1) echo '<span class="status-badge status-valide" title="Compte Actif et Validé">Validé</span>';
                    elseif ($medecin_details_data['valide'] == 0) echo '<span class="status-badge status-attente" title="En attente de validation">En attente</span>';
                 ?>
            </h1>
            <a href="gestion_medecins.php<?= !empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'gestion_medecins.php') !== false ? '?'.parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY) : '' ?>" 
               class="btn btn-sm secondary-action">← Retour à la liste des médecins</a>
        </div>

        <?php if ($flash_message_view_med): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_view_med) ?> alert-dismissible">
                <?= htmlspecialchars($flash_message_view_med) ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button> 
            </div>
        <?php endif; ?>

        <div class="profile-view-card card"> 
            <!-- STYLE: margin-bottom inline -->
            <div class="text-center" style="margin-bottom: 1.5rem;">
                <img src="<?= $medecin_details_data['photo'] ? '../' . htmlspecialchars($medecin_details_data['photo']) : '../assets/images/placeholder-medecin.jpg' ?>" 
                     alt="Photo de Dr. <?= htmlspecialchars($medecin_details_data['nom']) ?>" class="profile-photo">
            </div>

            <dl class="profile-details-grid">
                <dt><i class="fas fa-user icon-left"></i>Nom Complet :</dt>
                <dd>Dr. <?= htmlspecialchars($medecin_details_data['prenom'] . ' ' . $medecin_details_data['nom']) ?></dd>

                <dt><i class="fas fa-stethoscope icon-left"></i>Spécialité :</dt>
                <dd><?= htmlspecialchars($medecin_details_data['specialite']) ?></dd>
                
                <dt><i class="fas fa-envelope icon-left"></i>Email :</dt>
                <dd><a href="mailto:<?= htmlspecialchars($medecin_details_data['email']) ?>"><?= htmlspecialchars($medecin_details_data['email']) ?></a></dd>

                <dt><i class="fas fa-phone icon-left"></i>Téléphone :</dt>
                <dd><?= htmlspecialchars($medecin_details_data['telephone'] ?? 'Non fourni') ?></dd>

                <dt><i class="fas fa-map-marker-alt icon-left"></i>Adresse Cabinet :</dt>
                <dd><?= htmlspecialchars($medecin_details_data['adresse'] ?? 'Non fournie') ?></dd>
                
                 <dt><i class="fas fa-map-pin icon-left"></i>Coordonnées GPS :</dt>
                 <dd>
                     <?php if (!empty($medecin_details_data['latitude']) && !empty($medecin_details_data['longitude'])): ?>
                         Lat: <?= htmlspecialchars($medecin_details_data['latitude']) ?>, Lng: <?= htmlspecialchars($medecin_details_data['longitude']) ?>
                         <!-- STYLE: margin-left inline -->
                         <a href="https://www.google.com/maps?q=<?= htmlspecialchars($medecin_details_data['latitude']) ?>,<?= htmlspecialchars($medecin_details_data['longitude']) ?>" target="_blank" title="Voir sur Google Maps" class="link-icon" style="margin-left: 10px;"><i class="fas fa-external-link-alt"></i></a>
                    <?php else: ?>
                        <span class="text-muted">Non fournies</span>
                    <?php endif; ?>
                 </dd>

                <dt><i class="fas fa-clock icon-left"></i>Horaires Indicatifs :</dt>
                <dd><?= !empty($medecin_details_data['horaires']) ? nl2br(htmlspecialchars($medecin_details_data['horaires'])) : '<span class="text-muted">Non fournis</span>' ?></dd>

                <dt><i class="fas fa-file-medical icon-left"></i>Document Justificatif :</dt>
                <dd>
                    <?php if (!empty($medecin_details_data['document_justificatif'])): ?>
                        <a href="../<?= htmlspecialchars($medecin_details_data['document_justificatif']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-download icon-left"></i> Voir le document (<?= basename(htmlspecialchars($medecin_details_data['document_justificatif'])) ?>)
                        </a>
                    <?php else: ?>
                        <span class="text-muted">Aucun document fourni.</span>
                    <?php endif; ?>
                </dd>
                
                 <dt><i class="fas fa-user-check icon-left"></i>Statut du Compte :</dt>
                 <dd>
                    <?php 
                        if ($medecin_details_data['valide'] == 1) echo '<strong class="text-success">Validé et Actif</strong>'; // text-success est une classe Bootstrap-like
                        elseif ($medecin_details_data['valide'] == 0) echo '<strong class="text-warning">En attente de validation</strong>'; // text-warning
                    ?>
                 </dd>
                 <dt><i class="fas fa-calendar-alt icon-left"></i>Nombre de RDV Total :</dt>
                 <dd><?= $nombre_rdv_medecin ?> 
                    <?php if($nombre_rdv_medecin > 0): ?>
                        <!-- STYLE: margin-left inline -->
                        <a href="gestion_rdv.php?medecin=<?= $medecin_id_view ?>" title="Voir tous les RDV de ce médecin" class="link-icon" style="margin-left:10px;"><i class="fas fa-list-ul"></i></a>
                    <?php endif; ?>
                 </dd>
                 <dt><i class="fas fa-calendar-plus icon-left"></i>Date d'Inscription :</dt>
                 <dd><?= isset($medecin_details_data['date_inscription']) && $medecin_details_data['date_inscription'] ? date('d/m/Y H:i', strtotime($medecin_details_data['date_inscription'])) : 'N/A' ?></dd>
            </dl>

            <!-- STYLE: margin inline -->
            <hr style="margin: 2rem 0;">

             <div class="actions-toolbar text-center">
                 <?php if ($medecin_details_data['valide'] == 0): ?>
                    <a href="valider_medecin.php?id=<?= $medecin_details_data['id'] ?>&return_url=<?= urlencode('voir_medecin.php?id='.$medecin_details_data['id']) ?>" class="btn btn-success"><i class="fas fa-check icon-left"></i> Valider</a>
                    <a href="refuser_medecin.php?id=<?= $medecin_details_data['id'] ?>&return_url=gestion_medecins.php" class="btn btn-danger" onclick="return confirm('Refuser et supprimer cette demande d\'inscription ?')"><i class="fas fa-user-times icon-left"></i> Refuser/Supprimer</a>
                 <?php elseif ($medecin_details_data['valide'] == 1): ?>
                     <a href="invalider_medecin.php?id=<?= $medecin_details_data['id'] ?>&return_url=<?= urlencode('voir_medecin.php?id='.$medecin_details_data['id']) ?>" class="btn btn-warning" onclick="return confirm('Rendre ce médecin inactif ? Il ne sera plus visible et ne pourra plus recevoir de RDV.')"><i class="fas fa-user-slash icon-left"></i> Rendre Inactif</a>
                 <?php endif; ?>
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