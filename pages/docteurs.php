<?php
session_start();
// Assurez-vous que db.php est inclus si vous faites des requêtes ici, 
// mais pour la liste des spécialités, nous allons utiliser la liste codée.
require_once __DIR__ . '/../php/db.php'; // Pour la connexion PDO si besoin d'autres requêtes
require_once __DIR__ . '/../php/utils/app_settings.php'; // Pour NOM_APPLICATION, etc.
require_once __DIR__ . '/../php/utils/csrf_utils.php';

$specialite_filtree_get = trim(htmlspecialchars($_GET['specialite'] ?? ''));
$nom_medecin_filtre_get = trim(htmlspecialchars($_GET['nom_medecin'] ?? ''));
$localisation_filtree_get = trim(htmlspecialchars($_GET['localisation'] ?? ''));
$focus_med_id_get = filter_input(INPUT_GET, 'focus_med_id', FILTER_VALIDATE_INT);
$page_actuelle_get_php = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ["options" => ["default" => 1, "min_range" => 1]]);

$flash_message_doctors_page = $_SESSION['flash_message'] ?? null;
$flash_type_doctors_page = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Utilisation de la même liste de spécialités que index.php, incluant "Autre"
$specialites_liste_complete = [
    "Cardiologie", "Chirurgie cardiaque", "Chirurgie générale", "Chirurgien orthopédique",
    "Dermatologie", "Endocrinologie", "Gastro-entérologue", "Gériatre", "Interniste",
    "Médecine générale", "Néphrologue", "Neurochirurgien", "Oncologue", "Ophtalmologie",
    "ORL", "Pédiatrie", "Pneumologue", "Psychiatrie", "Radiologue", "Rhumatologue",
    "Autre" // Ajout de "Autre" ici
];
// sort($specialites_liste_complete); // Si vous voulez la trier alphabétiquement après ajout

$specialites_liste_form_doctors = $specialites_liste_complete;


$page_title_doctors = 'Nos Médecins';
$page_subtitle_doctors = "Trouvez le spécialiste qu'il vous faut et prenez rendez-vous facilement.";
$titre_parts = [];
if (!empty($specialite_filtree_get)) { $titre_parts[] = htmlspecialchars(ucfirst($specialite_filtree_get)) . (str_ends_with(strtolower($specialite_filtree_get), 'ue') ? 's' : (strtolower($specialite_filtree_get) === 'autre' ? 's professionnels' : 's')); }
if (!empty($nom_medecin_filtre_get)) { $titre_parts[] = 'recherche "' . htmlspecialchars($nom_medecin_filtre_get) . '"'; }
if (!empty($localisation_filtree_get)) { $titre_parts[] = 'près de "' . htmlspecialchars($localisation_filtree_get) . '"'; }

if (!empty($titre_parts)) {
    $page_title_doctors = implode(', ', $titre_parts);
} else {
    $page_title_doctors = 'Tous Nos Professionnels de Santé';
}
if ($specialite_filtree_get === "Autre"){
    $page_title_doctors = "Autres Professionnels de Santé"; // Titre spécifique pour "Autre"
}


$nb_rdv_nav_doc = 0; $nb_notif_nav_doc = 0;
if (isset($_SESSION['utilisateur_id']) && isset($_SESSION['type'])) {
    if ($_SESSION['type'] === 'patient') {
        try {
            if($pdo->query("SHOW TABLES LIKE 'rendez_vous'")->rowCount() > 0) {
                $stmt_rdv_nav_d = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE patient_id = :id AND ((date_rdv > CURDATE()) OR (date_rdv = CURDATE() AND heure_rdv >= CURTIME())) AND statut IN ('en attente', 'confirmé') AND supprime_par_patient = 0");
                $stmt_rdv_nav_d->execute([':id' => $_SESSION['utilisateur_id']]);
                $nb_rdv_nav_doc = $stmt_rdv_nav_d->fetchColumn();
            }

            if($pdo->query("SHOW TABLES LIKE 'notifications_patients'")->rowCount() > 0) {
                $stmt_notif_nav_d = $pdo->prepare("SELECT COUNT(*) FROM notifications_patients WHERE patient_id = :id AND lu = 0");
                $stmt_notif_nav_d->execute([':id' => $_SESSION['utilisateur_id']]);
                $nb_notif_nav_doc = $stmt_notif_nav_d->fetchColumn();
            }
        } catch (PDOException $e) {
            error_log("Erreur récupération badges nav (docteurs.php patient): " . $e->getMessage());
        }
    } elseif ($_SESSION['type'] === 'medecin') {
        try {
             if($pdo->query("SHOW TABLES LIKE 'rendez_vous'")->rowCount() > 0) {
                $stmt_rdv_att_nav_d = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE medecin_id = :id AND statut = 'en attente' AND ( (date_rdv > CURDATE()) OR (date_rdv = CURDATE() AND heure_rdv >= CURTIME()) ) AND supprime_par_medecin = 0");
                $stmt_rdv_att_nav_d->execute([':id' => $_SESSION['utilisateur_id']]);
                $nb_rdv_nav_doc = $stmt_rdv_att_nav_d->fetchColumn();
             }
        } catch (PDOException $e) {
            error_log("Erreur récupération badges nav (docteurs.php medecin): " . $e->getMessage());
        }
    }
}
$csrf_token_footer_comment = generate_csrf_token();
$nom_application_display_doc = defined('NOM_APPLICATION') ? htmlspecialchars(NOM_APPLICATION) : 'SANTE TV';
$email_contact_display_doc = defined('EMAIL_CONTACT_PRINCIPAL') ? htmlspecialchars(EMAIL_CONTACT_PRINCIPAL) : 'contact@example.com';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title_doctors ?> - <?= $nom_application_display_doc ?></title>
  <meta name="description" content="Liste des médecins et spécialistes disponibles sur <?= $nom_application_display_doc ?>. Filtrez par spécialité, nom ou localisation pour trouver le professionnel de santé adapté à vos besoins.">
  <link rel="stylesheet" href="../assets/css/styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <style>
    .doctor-card-map-container {
        width: 100%;
        height: 180px;
        background-color: var(--color-neutral-lightest);
        border-radius: var(--border-radius-sm);
        margin-top: var(--spacing-sm);
        margin-bottom: var(--spacing-md);
        border: 1px solid var(--color-neutral-light);
        cursor: pointer;
        position: relative;
    }
    .doctor-card-map-container .map-overlay-text {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: rgba(0,0,0,0.6);
        color: white;
        padding: var(--spacing-xs) var(--spacing-sm);
        border-radius: var(--border-radius-sm);
        font-size: 0.8em;
        display: none;
        pointer-events: none;
        z-index: 10;
    }
    .doctor-card-map-container:hover .map-overlay-text {
        display: block;
    }
    .doctor-card-map-container .leaflet-control-zoom,
    .doctor-card-map-container .leaflet-control-attribution {
        display: none !important;
    }
     #map-container-modal-large .leaflet-control-zoom {
        display: block !important;
    }
    #map-container-modal-large .leaflet-control-attribution {
        display: block !important;
        font-size: 0.8em !important;
    }
  </style>
</head>
<body class="body-page-doctors">

<header class="site-header">
    <div class="container">
        <div class="logo-branding">
            <a href="../index.php" title="<?= $nom_application_display_doc ?> Accueil">
                <img src="../assets/images/logo1.png" alt="<?= $nom_application_display_doc ?> Logo" id="logo-img">
                <span class="site-title"><?= $nom_application_display_doc ?></span>
            </a>
        </div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir/Fermer le menu" aria-expanded="false" aria-controls="main-nav">
            <span></span><span></span><span></span>
        </button>
        <nav class="main-navigation" id="main-nav">
            <ul>
                <li><a href="../index.php#accueil" class="nav-link">ACCUEIL</a></li>
                <li><a href="docteurs.php" class="nav-link active">NOS MEDECINS</a></li>
                <li><a href="../index.php?open_modal=%23modal-form" class="nav-link">REJOIGNEZ NOUS</a></li>
                <li><a href="../index.php#apropos" class="nav-link">A PROPOS</a></li>
                 <li><a href="contact.php" class="nav-link">CONTACT</a></li>
                <?php if (isset($_SESSION['utilisateur_id'])): ?>
                    <?php if ($_SESSION['type'] === 'patient'): ?>
                        <li><a href="../php/dashboard_patient.php" class="nav-link btn-header-connect">MON ESPACE <?php if($nb_rdv_nav_doc + $nb_notif_nav_doc > 0):?><span class="badge-notification"><?= ($nb_rdv_nav_doc + $nb_notif_nav_doc) ?></span><?php endif; ?></a></li>
                    <?php elseif ($_SESSION['type'] === 'medecin'): ?>
                        <li><a href="../php/espace_medecin.php" class="nav-link btn-header-connect">MON ESPACE <?php if($nb_rdv_nav_doc > 0):?><span class="badge-notification"><?= $nb_rdv_nav_doc ?></span><?php endif; ?></a></li>
                    <?php elseif ($_SESSION['type'] === 'admin'): ?>
                        <li><a href="../admin/dashboard_admin.php" class="nav-link btn-header-connect">ADMIN</a></li>
                    <?php endif; ?>
                     <li><a href="../php/deconnexion.php" class="nav-link" style="color: var(--color-warning);">DÉCONNEXION</a></li>
                <?php else: ?>
                    <li><a href="../index.php?open_modal=%23modal-connexion" class="nav-link btn-header-connect">SE CONNECTER</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content">
    <section class="doctors-listing-page section-padding">
        <div class="container">
            <div class="page-header text-center">
                <h1 class="page-main-title" id="doctorsPageTitle"><?= $page_title_doctors ?></h1>
                <p class="section-subtitle"><?= $page_subtitle_doctors ?></p>
            </div>

            <?php if ($flash_message_doctors_page): ?>
                <div class="alert alert-<?= htmlspecialchars($flash_type_doctors_page) ?> alert-dismissible" style="margin-bottom: 1.5rem;">
                    <?= htmlspecialchars($flash_message_doctors_page) ?>
                    <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button>
                </div>
            <?php endif; ?>

            <div class="filters-toolbar" id="doctorsPageFilterToolbar">
                <form id="doctorsPageFilterForm" class="filter-form-inline" method="GET" action="docteurs.php">
                    <div class="form-group search-group">
                        <label for="searchDoctorNamePage" class="sr-only">Rechercher par nom</label>
                        <div class="input-with-icon">
                            <i class="fas fa-user-md input-icon"></i>
                            <input type="search" id="searchDoctorNamePage" name="nom_medecin"
                                   placeholder="Nom, prénom du médecin..."
                                   value="<?= htmlspecialchars($nom_medecin_filtre_get) ?>" class="form-control">
                        </div>
                    </div>
                    <div class="form-group filter-group">
                        <label for="filterSpecialtyPage" class="sr-only">Filtrer par spécialité</label>
                        <div class="select-with-icon">
                             <i class="fas fa-stethoscope select-icon"></i>
                            <select id="filterSpecialtyPage" name="specialite" class="form-control">
                                <option value="">Toutes les spécialités</option>
                                <?php foreach($specialites_liste_form_doctors as $spec): ?>
                                    <option value="<?= htmlspecialchars($spec) ?>" <?= ($specialite_filtree_get === $spec) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($spec) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group filter-group">
                        <label for="filterLocationPage" class="sr-only">Filtrer par localisation</label>
                        <div class="input-with-icon">
                             <i class="fas fa-map-marker-alt input-icon"></i>
                            <input type="search" id="filterLocationPage" name="localisation"
                                   placeholder="Ville, adresse..."
                                   value="<?= htmlspecialchars($localisation_filtree_get) ?>"
                                   class="form-control">
                        </div>
                    </div>
                    <button type="submit" class="btn primary-action filter-submit-button">
                        <i class="fas fa-filter icon-left"></i>Filtrer
                    </button>
                    <?php if (!empty($specialite_filtree_get) || !empty($nom_medecin_filtre_get) || !empty($localisation_filtree_get)): ?>
                        <a href="docteurs.php" id="resetFiltersButton" class="btn secondary-action filter-reset-button" title="Effacer les filtres">
                            <i class="fas fa-undo"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="doctor-list" id="doctorListContainer">
                <p id="loading-doctors" class="info-message text-center">
                    <i class="fas fa-spinner fa-spin icon-left"></i> Chargement des médecins...
                </p>
                <p id="no-doctors-found" class="info-message text-center" style="display:none;">
                    <i class="fas fa-search-minus icon-left"></i>Aucun médecin ne correspond à vos critères de recherche.
                </p>
            </div>

            <div id="doctorsPaginationControls" class="pagination-controls-wrapper" style="display:none;">
                <span id="paginationInfoDoctors"></span>
                <nav id="paginationNavDoctors" class="pagination-nav"></nav>
            </div>

            <div id="map-modal-large" class="modal" role="dialog" aria-modal="true" aria-labelledby="map-modal-large-title-text">
                <div class="modal-content map-modal-content">
                    <button class="close-modal-button" aria-label="Fermer la carte">×</button>
                    <h3 id="map-modal-large-title-text" class="form-title">Localisation Détaillée</h3>
                    <div id="map-container-modal-large" style="width: 100%; height: 500px; background-color: #f0f0f0; border-radius: var(--border-radius-md);"></div>
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-section footer-about"><h3 class="footer-title">À propos</h3><p><?= $nom_application_display_doc ?> simplifie la prise de rendez-vous médicaux...</p></div>
            <div class="footer-section footer-links"><h3 class="footer-title">Liens rapides</h3><ul><li><a href="../index.php#accueil">Accueil</a></li><li><a href="docteurs.php" class="active">Nos médecins</a></li><li><a href="../index.php#apropos">À Propos</a></li> <li><a href="contact.php">Contact</a></li><li><a href="faq.php">FAQ</a></li></ul></div>
            <div class="footer-section footer-contact"><h3 class="footer-title">Contact</h3><p><i class="fas fa-envelope icon-left"></i><a href="mailto:<?= $email_contact_display_doc ?>"><?= $email_contact_display_doc ?></a></p><p><i class="fas fa-phone icon-left"></i><a href="tel:+212656629464">+212 6 56 62 94 64</a></p></div>
            <div class="footer-section footer-comment-form"><h3 class="footer-title">Laissez un commentaire</h3><form id="commentFormFooterDoctors" action="../php/soumettre_commentaire.php" method="POST" class="user-form"><input type="hidden" name="form_origin_commentaire" value="../pages/docteurs.php#commentFormFooterDoctors"><?= csrf_input_field($csrf_token_footer_comment) ?><div class="form-group"><label for="nom_comm_doc" class="sr-only">Nom</label><input type="text" id="nom_comm_doc" name="nom_commentaire" placeholder="Votre nom" required class="form-control"></div><div class="form-group"><label for="msg_comm_doc" class="sr-only">Avis</label><textarea id="msg_comm_doc" name="message_commentaire" placeholder="Votre avis..." required rows="3" class="form-control"></textarea></div><button type="submit" class="submit-button primary-action btn-sm btn-block">Envoyer</button></form></div>
        </div>
        <div class="footer-social-admin"><div class="social-icons"><a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a><a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a><a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a></div><div class="admin-space-link"><a href="admin-login.php">Espace Administrateur</a></div></div>
        <div class="footer-bottom"><p class="copyright-text">© <span id="footer-year"><?= date('Y') ?></span> <?= $nom_application_display_doc ?>. Tous droits réservés.</p></div>
    </div>
</footer>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    const initialFiltersFromPHP = {
        specialite: <?= json_encode($specialite_filtree_get) ?>,
        nom_medecin: <?= json_encode($nom_medecin_filtre_get) ?>,
        localisation: <?= json_encode($localisation_filtree_get) ?>,
        page: <?= json_encode($page_actuelle_get_php) ?>
    };
     // Le code JS spécifique à docteurs.php (loadDoctors, setupDoctorsPagination, etc.)
    // est déjà dans assets/js/script.js et sera appelé par initDoctorsPage().
</script>
</body>
</html>