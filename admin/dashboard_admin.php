<?php
session_start();
require '../php/db.php'; 
require_once '../php/utils/app_settings.php';

if (!isset($_SESSION['admin_id'])) { 
    $_SESSION['flash_message_login'] = "Accès refusé. Veuillez vous connecter en tant qu'administrateur.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/admin-login.php'); 
    exit;
}
$admin_id_dashboard = $_SESSION['admin_id'];

$stmt_admin_info_dash = $pdo->prepare("SELECT nom FROM admins WHERE id = ?");
$stmt_admin_info_dash->execute([$admin_id_dashboard]);
$admin_data_dash = $stmt_admin_info_dash->fetch();
$admin_nom_display_dash = $admin_data_dash ? htmlspecialchars($admin_data_dash['nom']) : 'Administrateur';

$nb_patients_stat = $nb_medecins_total_stat = $nb_medecins_valides_stat = $nb_medecins_attente_stat = 0;
$nb_rdv_actifs_stat = $nb_rdv_total_stat = $nb_commentaires_attente_stat = $nb_commentaires_total_stat = 0;

try {
    // Vérifier l'existence des tables avant de les requêter
    $tables_to_check_stats = ['patients', 'medecins', 'rendez_vous', 'commentaires'];
    $existing_tables_stats = [];
    foreach ($tables_to_check_stats as $table_stat) {
        if ($pdo->query("SHOW TABLES LIKE '$table_stat'")->rowCount() > 0) {
            $existing_tables_stats[$table_stat] = true;
        } else {
            error_log("Table '$table_stat' non trouvée pour les statistiques du dashboard admin.");
        }
    }

    if (isset($existing_tables_stats['patients'])) {
        $nb_patients_stat = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    }
    if (isset($existing_tables_stats['medecins'])) {
        $nb_medecins_total_stat = $pdo->query("SELECT COUNT(*) FROM medecins")->fetchColumn();
        $nb_medecins_valides_stat = $pdo->query("SELECT COUNT(*) FROM medecins WHERE valide = 1")->fetchColumn();
        $nb_medecins_attente_stat = $pdo->query("SELECT COUNT(*) FROM medecins WHERE valide = 0")->fetchColumn();
    }
    if (isset($existing_tables_stats['rendez_vous'])) {
        $nb_rdv_actifs_stat = $pdo->query(
            "SELECT COUNT(*) FROM rendez_vous 
             WHERE statut IN ('en attente', 'confirmé') 
             AND CONCAT(date_rdv, ' ', heure_rdv) >= NOW()"
        )->fetchColumn();
        $nb_rdv_total_stat = $pdo->query("SELECT COUNT(*) FROM rendez_vous")->fetchColumn();
    }
    if (isset($existing_tables_stats['commentaires'])) {
        $nb_commentaires_attente_stat = $pdo->query("SELECT COUNT(*) FROM commentaires WHERE statut = 'en attente'")->fetchColumn();
        $nb_commentaires_total_stat = $pdo->query("SELECT COUNT(*) FROM commentaires")->fetchColumn();
    }

} catch (PDOException $e) {
    error_log("Erreur PDO récupération stats admin dashboard: " . $e->getMessage());
    $_SESSION['flash_message'] = "Erreur lors de la récupération des statistiques.";
    $_SESSION['flash_type'] = "error";
}

$medecins_attente_dashboard_list = [];
if (isset($existing_tables_stats['medecins'])) { // Ne requêter que si la table medecins existe
    try {
        // Vérifier si la colonne date_inscription existe
        $colonnes_medecins_dash = $pdo->query("DESCRIBE medecins")->fetchAll(PDO::FETCH_COLUMN);
        $order_by_clause_dash = in_array('date_inscription', $colonnes_medecins_dash) ? "ORDER BY date_inscription DESC" : "ORDER BY id DESC";

        $stmt_med_attente_dash = $pdo->query(
            "SELECT id, nom, prenom, email, specialite" . (in_array('date_inscription', $colonnes_medecins_dash) ? ", date_inscription" : "") .
             " FROM medecins 
             WHERE valide = 0 
             $order_by_clause_dash
             LIMIT 5"
        );
        $medecins_attente_dashboard_list = $stmt_med_attente_dash->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
         error_log("Erreur PDO récupération médecins en attente dashboard: " . $e->getMessage());
    }
}

$flash_message_admin_dash = $_SESSION['flash_message'] ?? null;
$flash_type_admin_dash = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']); 
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Administrateur - SANTE TV</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tiny.cloud/1/4amskh9mj3cii8jraizm25oi6k5kvplfgmzr48dm2b52fgj7/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body class="admin-dashboard-page"> 

<header class="site-header admin-header">
    <div class="container">
         <div class="logo-branding">
            <a href="dashboard_admin.php" title="Tableau de Bord Admin SANTE TV"> 
                <img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img">
                <span class="site-title">Admin SANTE TV</span>
            </a>
        </div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav">
            <span></span><span></span><span></span>
        </button>
         <nav class="main-navigation admin-navigation" id="main-nav">
            <ul>
                <li><a href="dashboard_admin.php" class="nav-link active">Dashboard</a></li>
                <li><a href="gestion_medecins.php" class="nav-link">Médecins</a></li>
                <li><a href="gestion_patients.php" class="nav-link">Patients</a></li>
                <li><a href="gestion_rdv.php" class="nav-link">Rendez-vous</a></li>
                <li><a href="envoyer_emails_masse.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'envoyer_emails_masse.php' || basename($_SERVER['PHP_SELF']) == 'envoyer_email_specifique.php') ? 'active' : ''; ?>"> <i class="fas fa-mail-bulk icon-left"></i>Email en Masse</a></li>
                    <?php if($nb_commentaires_attente_stat > 0): ?><span class="badge-notification"><?= $nb_commentaires_attente_stat ?></span><?php endif; ?>
                </a></li>
                <li><a href="historique_app.php" class="nav-link">Historique</a></li>
                <li><a href="deconnexion_admin.php" class="nav-link btn btn-sm btn-danger">Déconnexion</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content admin-page-container section-padding"> 
    <div class="container">
        <div class="page-header">
            <h1 class="page-main-title">Tableau de Bord Administrateur</h1>
            <p class="welcome-message">Bienvenue, <span class="admin-name-placeholder"><?= $admin_nom_display_dash ?></span> ! Gérez la plateforme SANTE TV.</p>
        </div>

        <?php if ($flash_message_admin_dash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_admin_dash) ?> alert-dismissible">
                <?= htmlspecialchars($flash_message_admin_dash) ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button> 
            </div>
        <?php endif; ?>

        <section class="statistics-section mb-4"> 
            <h2 class="section-title visually-hidden">Statistiques Globales</h2> 
            <div class="summary-cards-grid">
                <div class="summary-card">
                    <h3 class="summary-card-title"><i class="fas fa-users icon-left"></i> Patients Inscrits</h3>
                    <p class="summary-card-value"><?= $nb_patients_stat ?></p>
                    <a href="gestion_patients.php" class="summary-card-link">Gérer les patients →</a>
                </div>
                 <div class="summary-card">
                    <h3 class="summary-card-title"><i class="fas fa-user-md icon-left"></i> Médecins (Total)</h3>
                    <p class="summary-card-value"><?= $nb_medecins_total_stat ?></p>
                    <a href="gestion_medecins.php" class="summary-card-link">Gérer les médecins →</a>
                </div>
                <div class="summary-card <?= ($nb_medecins_attente_stat > 0) ? 'has-pending-border' : 'success-border' ?>">
                    <h3 class="summary-card-title"><i class="fas fa-user-clock icon-left"></i> Médecins en Attente</h3>
                    <!-- STYLE: color inline -->
                    <p class="summary-card-value" style="color: <?= ($nb_medecins_attente_stat > 0) ? 'var(--color-warning)' : 'var(--color-success)' ?>;"><?= $nb_medecins_attente_stat ?></p>
                    <?php if($nb_medecins_attente_stat > 0): ?>
                        <a href="gestion_medecins.php?status=attente" class="summary-card-link">Traiter les demandes →</a>
                    <?php else: ?>
                        <!-- STYLE: color inline -->
                        <span class="summary-card-link" style="color:var(--text-color-muted);">Aucune demande</span>
                    <?php endif; ?>
                </div>
                <div class="summary-card success-border"> 
                    <h3 class="summary-card-title"><i class="fas fa-user-check icon-left"></i> Médecins Validés</h3>
                    <!-- STYLE: color inline -->
                    <p class="summary-card-value" style="color:var(--color-success);"><?= $nb_medecins_valides_stat ?></p>
                </div>
                <div class="summary-card">
                    <h3 class="summary-card-title"><i class="fas fa-calendar-check icon-left"></i> RDV Actifs</h3>
                    <p class="summary-card-value"><?= $nb_rdv_actifs_stat ?></p>
                     <a href="gestion_rdv.php" class="summary-card-link">Gérer les RDV →</a>
                </div>
                 <div class="summary-card <?= ($nb_commentaires_attente_stat > 0) ? 'has-pending-border' : 'info-border' ?>">
                    <h3 class="summary-card-title"><i class="fas fa-comments icon-left"></i> Commentaires en Attente</h3>
                    <!-- STYLE: color inline -->
                    <p class="summary-card-value" style="color: <?= ($nb_commentaires_attente_stat > 0) ? 'var(--color-warning)' : 'var(--color-primary-dark)' ?>;"><?= $nb_commentaires_attente_stat ?></p>
                    <?php if($nb_commentaires_attente_stat > 0): ?>
                     <a href="gestion_commentaires.php" class="summary-card-link">Modérer les commentaires →</a>
                    <?php else: ?>
                        <!-- STYLE: color inline -->
                        <span class="summary-card-link" style="color:var(--text-color-muted);">Boîte vide</span>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="pending-doctors-section">
             <div class="section-header">
                <!-- STYLE: font-size inline -->
                <h2 class="section-title">Dernières Demandes d'Inscription Médecin <span class="text-muted" style="font-size:0.8em;">(<?= count($medecins_attente_dashboard_list) ?> affichées)</span></h2>
                <?php if($nb_medecins_attente_stat > 0): ?>
                    <a href="gestion_medecins.php?status=attente" class="btn btn-sm btn-outline-primary">Voir toutes les demandes (<?= $nb_medecins_attente_stat ?>)</a>
                <?php endif; ?>
             </div>

            <?php if (count($medecins_attente_dashboard_list) > 0): ?>
                <div class="table-responsive-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nom & Prénom</th>
                                <th>Email</th>
                                <th>Spécialité</th>
                                <th>Date Inscription</th> 
                                <th class="actions-cell">Actions Rapides</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($medecins_attente_dashboard_list as $med_att): ?>
                            <tr>
                                <td><?= htmlspecialchars($med_att['prenom'] . ' ' . $med_att['nom']) ?></td>
                                <td><?= htmlspecialchars($med_att['email']) ?></td>
                                <td><?= htmlspecialchars($med_att['specialite']) ?></td>
                                <td><?= isset($med_att['date_inscription']) ? date('d/m/Y H:i', strtotime($med_att['date_inscription'])) : 'N/A' ?></td>
                                <td class="actions-cell">
                                    <a href="valider_medecin.php?id=<?= $med_att['id'] ?>" class="btn btn-sm btn-success" title="Valider ce médecin"><i class="fas fa-check"></i></a>
                                    <a href="refuser_medecin.php?id=<?= $med_att['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Refuser et supprimer cette demande ?')" title="Refuser/Supprimer"><i class="fas fa-times"></i></a>
                                    <a href="voir_medecin.php?id=<?= $med_att['id'] ?>" class="btn btn-sm btn-info" title="Voir détails complets"><i class="fas fa-eye"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-success text-center">
                    <i class="fas fa-thumbs-up icon-left"></i>Félicitations ! Aucune demande d'inscription médecin en attente pour le moment.
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<footer class="site-footer admin-footer">
   <div class="container">
        <p class="copyright-text text-center">© <span id="footer-year"></span> SANTE TV - Espace Administration.</p>
    </div>
</footer>

<script src="../assets/js/script.js"></script> 
</body>
</html>