<?php
session_start();
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 
// email_template.php n'est pas directement utilisé ici car cette page AFFICHE des notifications, elle n'envoie pas d'email.
// email_functions.php non plus, sauf si une action sur cette page devait déclencher un email.

if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'patient') {
    $_SESSION['flash_message_login'] = "Accès non autorisé. Veuillez vous connecter.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/connexion.php'); 
    exit;
}
$patient_id = $_SESSION['utilisateur_id'];

$stmt_patient_data_notif_page = $pdo->prepare("SELECT nom, prenom FROM patients WHERE id = ?");
$stmt_patient_data_notif_page->execute([$patient_id]);
$patient_data_notif_page = $stmt_patient_data_notif_page->fetch(PDO::FETCH_ASSOC);
$nom_patient_display_notif_page = $patient_data_notif_page ? htmlspecialchars($patient_data_notif_page['prenom'] . ' ' . $patient_data_notif_page['nom']) : htmlspecialchars($_SESSION['nom'] ?? 'Patient');

$notifications_liste_patient = [];
$table_notif_exists_check = $pdo->query("SHOW TABLES LIKE 'notifications_patients'")->rowCount() > 0;

if ($table_notif_exists_check) {
    try {
        $stmt_notifications = $pdo->prepare("
            SELECT id, message, type_notification, lien, details_rdv_id, date_creation, lu
            FROM notifications_patients
            WHERE patient_id = :patient_id
            ORDER BY date_creation DESC
        "); 
        $stmt_notifications->execute([':patient_id' => $patient_id]);
        $notifications_liste_patient = $stmt_notifications->fetchAll(PDO::FETCH_ASSOC);

        $ids_notifications_non_lues_page = [];
        foreach ($notifications_liste_patient as $notif_item) {
            if (!$notif_item['lu']) {
                $ids_notifications_non_lues_page[] = $notif_item['id'];
            }
        }

        if (!empty($ids_notifications_non_lues_page)) {
            $in_clause_ids_notif_page = implode(',', array_fill(0, count($ids_notifications_non_lues_page), '?'));
            // Marquer comme lues les notifications qui sont affichées sur cette page
            $sql_mark_notif_lu_page = "UPDATE notifications_patients SET lu = 1 WHERE id IN ($in_clause_ids_notif_page) AND patient_id = ?";
            
            $stmt_mark_notif_lu_page = $pdo->prepare($sql_mark_notif_lu_page);
            $params_mark_notif_lu_page = array_merge($ids_notifications_non_lues_page, [$patient_id]);
            $stmt_mark_notif_lu_page->execute($params_mark_notif_lu_page);
        }
    } catch (PDOException $e) {
        error_log("Erreur récupération/marquage notifications pour patient $patient_id (messages_patient.php): " . $e->getMessage());
    }
} else {
    error_log("La table 'notifications_patients' est manquante.");
}

$flash_message_notifications_page = $_SESSION['flash_message'] ?? null;
$flash_type_notifications_page = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Badges pour la navigation
$nb_rdv_nav_notif = 0;
$nb_notif_nav_display_badge = 0; // Sera 0 après le chargement de cette page car on marque tout comme lu

try {
    $stmt_rdv_nav_notif_count = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE patient_id = :id AND ((date_rdv > CURDATE()) OR (date_rdv = CURDATE() AND heure_rdv >= CURTIME())) AND statut IN ('en attente', 'confirmé') AND supprime_par_patient = 0");
    $stmt_rdv_nav_notif_count->execute([':id' => $patient_id]);
    $nb_rdv_nav_notif = $stmt_rdv_nav_notif_count->fetchColumn();
} catch (PDOException $e) {
    error_log("Erreur comptage RDV nav pour patient $patient_id (messages_patient.php): " . $e->getMessage());
}


$csrf_token_delete_notif = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Notifications - <?= $nom_patient_display_notif_page ?> - SANTE TV</title>
    <meta name="description" content="Consultez vos notifications importantes concernant vos rendez-vous et votre compte sur SANTE TV.">
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tiny.cloud/1/4amskh9mj3cii8jraizm25oi6k5kvplfgmzr48dm2b52fgj7/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body class="user-dashboard-page body-page-messages-patient"> 

<header class="site-header">
    <div class="container">
        <div class="logo-branding"><a href="../index.php" title="Accueil SANTE TV"><img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img"><span class="site-title">SANTE TV</span></a></div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav"><span></span><span></span><span></span></button>
        <nav class="main-navigation" id="main-nav">
             <ul>
                <li><a href="dashboard_patient.php" class="nav-link">Mon Espace</a></li>
                <li><a href="../pages/docteurs.php" class="nav-link">Trouver un Médecin</a></li>
                <li><a href="mes_rendez_vous_patient.php" class="nav-link">Mes Rendez-vous
                    <?php if($nb_rdv_nav_notif > 0): ?><span class="badge-notification"><?= $nb_rdv_nav_notif ?></span><?php endif; ?>
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

<main class="main-content section-padding">
    <div class="container">
        <div class="page-header">
            <h1 class="page-main-title">Mes Notifications</h1>
            <p class="section-subtitle">Retrouvez ici les dernières mises à jour concernant vos activités sur la plateforme.</p>
        </div>

        <?php if ($flash_message_notifications_page): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_notifications_page) ?> alert-dismissible">
                <?= htmlspecialchars($flash_message_notifications_page) ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button>
            </div>
        <?php endif; ?>
        
        <section class="messages-section card" style="margin-top:1.5rem;"> 
            <h2 class="section-title card-title" style="display: none;">Liste des Notifications</h2>
            <?php if (count($notifications_liste_patient) > 0): ?>
                <ul class="messages-list notification-list"> 
                    <?php foreach ($notifications_liste_patient as $notif): ?>
                        <?php
                            $icon_class_notif = 'fas fa-info-circle'; 
                            $color_style_notif = 'color: var(--color-info);'; 
                            $notif_type_for_class = htmlspecialchars($notif['type_notification'] ?? 'info');

                            switch ($notif['type_notification']) {
                                case 'succes': case 'rdv_confirme': 
                                    $icon_class_notif = 'fas fa-check-circle'; $color_style_notif = 'color: var(--color-success);'; break;
                                case 'erreur': case 'rdv_annule': case 'rdv_refuse':
                                    $icon_class_notif = 'fas fa-exclamation-triangle'; $color_style_notif = 'color: var(--color-danger);'; break;
                                case 'rappel':
                                    $icon_class_notif = 'fas fa-bell'; $color_style_notif = 'color: var(--color-warning);'; break;
                            }
                        ?>
                        <li class="message-item system-notification <?= !$notif['lu'] ? 'message-unread-initial' : '' ?> notification-type-<?= $notif_type_for_class ?>">
                            <div class="message-header">
                                <span class="message-sender">
                                    <i class="<?= $icon_class_notif ?> icon-left" style="<?= $color_style_notif ?>"></i>
                                    Notification Système
                                </span>
                                <span class="message-date">
                                   <i class="fas fa-clock icon-left"></i> <?= date('d/m/Y \à H:i', strtotime($notif['date_creation'])) ?>
                                </span>
                            </div>
                            <div class="message-content">
                                <p><?= nl2br(htmlspecialchars($notif['message'])) ?></p>
                                <?php if (!empty($notif['lien']) || $notif['details_rdv_id']): ?>
                                    <p style="margin-top: 0.75rem;">
                                        <a href="<?= !empty($notif['lien']) ? htmlspecialchars(str_starts_with($notif['lien'], 'http') ? $notif['lien'] : '../' . ltrim($notif['lien'], '/')) : ('mes_rendez_vous_patient.php#rdv-row-' . $notif['details_rdv_id']) ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                           <?= $notif['details_rdv_id'] ? 'Voir le Rendez-vous' : 'Plus d\'infos' ?> →
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="message-actions">
                                <form action="supprimer_notification.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_delete_notif) ?>">
                                    <input type="hidden" name="notification_id" value="<?= $notif['id'] ?>">
                                    <input type="hidden" name="user_type" value="patient">
                                    <button type="submit" class="btn btn-xs btn-danger" 
                                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette notification ? Cette action est irréversible.');"
                                            title="Supprimer cette notification">
                                        <i class="fas fa-trash-alt"></i> Supprimer
                                    </button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php elseif(!$table_notif_exists_check): ?>
                 <p class="no-messages text-center" style="padding: 2rem 0;"><i class="fas fa-exclamation-triangle icon-left"></i>Le système de notification est actuellement indisponible.</p>
            <?php else: ?>
                <p class="no-messages text-center" style="padding: 2rem 0;"><i class="fas fa-folder-open icon-left"></i>Vous n'avez aucune notification pour le moment.</p>
            <?php endif; ?>
        </section>
        
    </div>
</main>

<footer class="site-footer">
   <div class="container"><p class="copyright-text text-center">© <span id="footer-year"></span> SANTE TV Plus. Tous droits réservés.</p></div>
</footer>

<script src="../assets/js/script.js"></script> 
</body>
</html>