<?php
session_start();
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 

if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'medecin') {
    $_SESSION['flash_message_login'] = "Accès non autorisé.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/connexion.php'); 
    exit;
}
$medecin_id_profil = $_SESSION['utilisateur_id'];

$stmt_medecin_profil_db = $pdo->prepare("SELECT * FROM medecins WHERE id = ?");
$stmt_medecin_profil_db->execute([$medecin_id_profil]);
$medecin_db_data_profil = $stmt_medecin_profil_db->fetch(PDO::FETCH_ASSOC);

if (!$medecin_db_data_profil) {
    $_SESSION['flash_message_login'] = "Erreur: Profil médecin introuvable.";
    $_SESSION['flash_type_login'] = "error";
    session_unset(); 
    session_destroy();
    header('Location: ../pages/connexion.php');
    exit;
}
$nom_medecin_display_profil_header = htmlspecialchars("Dr. " . ($medecin_db_data_profil['prenom'] ?? '') . ' ' . ($medecin_db_data_profil['nom'] ?? 'Médecin'));

$form_data_info_profil_med = $_SESSION['form_data_maj_profil_med'] ?? $medecin_db_data_profil; 
$form_errors_info_profil_med = $_SESSION['form_errors_maj_profil_med'] ?? [];
unset($_SESSION['form_data_maj_profil_med'], $_SESSION['form_errors_maj_profil_med']);

$form_errors_pass_profil_med = $_SESSION['form_errors_change_pass_med'] ?? [];
unset($_SESSION['form_errors_change_pass_med']);

$profil_page_flash_message_med = $_SESSION['flash_message'] ?? null;
$profil_page_flash_type_med = $_SESSION['flash_type'] ?? '';
if ($medecin_db_data_profil['valide'] != 1 && !$profil_page_flash_message_med) { 
    $profil_page_flash_message_med = "Votre compte est en attente de validation. Vous pouvez mettre à jour vos informations, mais elles ne seront pleinement effectives et visibles publiquement qu'après validation par un administrateur.";
    $profil_page_flash_type_med = "warning";
}
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$csrf_token_info_profil_med = generate_csrf_token();
$csrf_token_pass_profil_med = $csrf_token_info_profil_med; 

$nb_rdv_att_nav_profil_med = 0; 
$nb_msg_nav_profil_med = 0; 
if ($medecin_db_data_profil['valide'] == 1) {
    try {
        $stmt_rdv_att_nav_profil_med = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE medecin_id = :id AND statut = 'en attente' AND ( (date_rdv > CURDATE()) OR (date_rdv = CURDATE() AND heure_rdv >= CURTIME()) )");
        $stmt_rdv_att_nav_profil_med->execute([':id' => $medecin_id_profil]);
        $nb_rdv_att_nav_profil_med = $stmt_rdv_att_nav_profil_med->fetchColumn();

        $table_messages_exists_profil_nav = $pdo->query("SHOW TABLES LIKE 'messages'")->rowCount() > 0;
        if ($table_messages_exists_profil_nav) {
            $check_col_msg_profil_med = $pdo->query("SHOW COLUMNS FROM messages LIKE 'lu_par_medecin'"); 
            if ($check_col_msg_profil_med->fetch()) { 
                $stmt_msg_nav_profil_med = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = :med_id AND lu_par_medecin = 0"); 
                $stmt_msg_nav_profil_med->execute([':med_id' => $medecin_id_profil]); 
                $nb_msg_nav_profil_med = $stmt_msg_nav_profil_med->fetchColumn(); 
            }
        }
    } catch (PDOException $e) {
        error_log("Erreur récupération badges nav (profil_medecin.php): " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil Professionnel - <?= $nom_medecin_display_profil_header ?> - SANTE TV</title>
    <meta name="description" content="Gérez votre profil professionnel, vos informations de contact, horaires, photo et mot de passe sur votre espace médecin SANTE TV.">
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-geosearch@3.11.0/dist/geosearch.css" /> 
    <style>
        #mapPickLocation {
            height: 350px;
            width: 100%;
            margin-bottom: var(--spacing-md);
            border-radius: var(--border-radius-md);
            border: 1px solid var(--color-neutral-light);
        }
        .leaflet-geosearch-bar {
            z-index: 1000 !important; 
        }
         /* Styles pour les mini-cartes sur les fiches (si utilisées ailleurs) */
        .doctor-card-map-container {
            width: 100%; height: 180px; background-color: var(--color-neutral-lightest); 
            border-radius: var(--border-radius-sm); margin-top: var(--spacing-sm);
            margin-bottom: var(--spacing-md); border: 1px solid var(--color-neutral-light);
            cursor: pointer; position: relative; 
        }
        .doctor-card-map-container .map-overlay-text {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            background-color: rgba(0,0,0,0.6); color: white; padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--border-radius-sm); font-size: 0.8em;
            display: none; pointer-events: none; z-index: 10; 
        }
        .doctor-card-map-container:hover .map-overlay-text { display: block; }
        .doctor-card-map-container .leaflet-control-zoom,
        .doctor-card-map-container .leaflet-control-attribution { display: none !important; }
    </style>
</head>
<body class="profile-page user-dashboard-page"> 

<header class="site-header">
    <div class="container">
        <div class="logo-branding"><a href="../index.php" title="Accueil SANTE TV"><img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img"><span class="site-title">SANTE TV</span></a></div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav"><span></span><span></span><span></span></button>
        <nav class="main-navigation" id="main-nav">
             <ul>
                <li><a href="espace_medecin.php" class="nav-link">Mon Espace</a></li>
                <li><a href="mes_rendez_vous_medecin.php" class="nav-link">Mes Rendez-vous <?php if($nb_rdv_att_nav_profil_med > 0): ?><span class="badge-notification"><?= $nb_rdv_att_nav_profil_med ?></span><?php endif; ?></a></li>
                <li><a href="gestion_disponibilites_medecin.php" class="nav-link">Mes Disponibilités</a></li>
                <li>
                    <a href="messages_medecin.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'messages_medecin.php') ? 'active' : ''; ?>">Messagerie Reçue
                        <?php if(isset($nb_messages_non_lus_med_dash) && $nb_messages_non_lus_med_dash > 0 && $compte_medecin_est_valide): // Adaptez le nom de la variable si besoin ?>
                        <span class="badge-notification"><?= $nb_messages_non_lus_med_dash ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="profil_medecin.php" class="nav-link active">Mon Profil</a></li>
                <li><a href="deconnexion.php" class="nav-link btn btn-sm btn-danger">Déconnexion</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content section-padding">
    <div class="container">
        <div class="page-header">
            <h1 class="page-main-title">Mon Profil Professionnel</h1>
             <a href="espace_medecin.php" class="btn btn-sm secondary-action">← Retour à Mon Espace</a>
        </div>

        <?php if ($profil_page_flash_message_med): ?>
            <div class="alert alert-<?= htmlspecialchars($profil_page_flash_type_med) ?> alert-dismissible" id="profilePageFlashMessage">
                <?= htmlspecialchars($profil_page_flash_message_med) ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button>
            </div>
        <?php endif; ?>

        <div class="profile-content-grid"> 
            <section id="infosProSection" class="profile-section card">
                <h2 class="section-title"><i class="fas fa-id-card icon-left"></i>Informations Publiques et de Contact</h2>
                <form id="profileUpdateFormMedecin" action="maj_profil.php" method="POST" enctype="multipart/form-data" class="user-form">
                    <?= csrf_input_field() ?>
                    <input type="hidden" name="form_origin_profil" value="profil_medecin.php#infosProSection">
                    <?php if (isset($form_errors_info_profil_med['_general'])): ?><p class="alert alert-danger"><?= htmlspecialchars($form_errors_info_profil_med['_general']) ?></p><?php endif; ?>

                    <div class="current-profile-picture-container text-center">
                        <img src="<?= $form_data_info_profil_med['photo'] ? '../' . htmlspecialchars($form_data_info_profil_med['photo']) : '../assets/images/placeholder-medecin.jpg' ?>" 
                             alt="Photo de profil actuelle" class="profile-photo-display" id="currentProfilePicMedecin" 
                             onclick="document.getElementById('photoInputMedecin').click();" title="Cliquez pour changer la photo">
                        <img src="#" alt="Aperçu nouvelle photo" id="photoPreviewMedecin" class="profile-photo-display" style="display:none;">
                    </div>
                    <div class="form-group">
                        <label for="photoInputMedecin" class="btn btn-sm secondary-action" style="display:table; margin: 0 auto 1rem auto; cursor:pointer;">
                            <i class="fas fa-camera icon-left"></i>Changer ma photo de profil
                        </label>
                        <input type="file" id="photoInputMedecin" name="photo" class="form-control-file <?= isset($form_errors_info_profil_med['photo']) ? 'input-error' : '' ?>" accept="image/jpeg,image/png,image/gif" style="display:none;" onchange="previewProfilePhoto(event, 'photoPreviewMedecin', 'currentProfilePicMedecin')">
                        <small class="form-note text-center">Formats: JPG, PNG, GIF. Max 2MB.</small>
                        <small class="form-error-message"><?= htmlspecialchars($form_errors_info_profil_med['photo'] ?? '') ?></small>
                    </div>

                    <div class="form-group"><label for="nom-profil-med">Nom : <span class="text-danger">*</span></label><input type="text" id="nom-profil-med" name="nom" class="form-control <?= isset($form_errors_info_profil_med['nom']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_info_profil_med['nom'] ?? '') ?>" required><small class="form-error-message"><?= htmlspecialchars($form_errors_info_profil_med['nom'] ?? '') ?></small></div>
                    <div class="form-group"><label for="prenom-profil-med">Prénom : <span class="text-danger">*</span></label><input type="text" id="prenom-profil-med" name="prenom" class="form-control <?= isset($form_errors_info_profil_med['prenom']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_info_profil_med['prenom'] ?? '') ?>" required><small class="form-error-message"><?= htmlspecialchars($form_errors_info_profil_med['prenom'] ?? '') ?></small></div>
                    <div class="form-group">
                        <label for="specialite-profil-med">Spécialité :</label>
                        <input type="text" id="specialite-profil-med" class="form-control readonly-input" value="<?= htmlspecialchars($medecin_db_data_profil['specialite']) ?>" readonly title="La spécialité est définie lors de l'inscription et validée par l'administration.">
                        <small class="form-note">Pour un changement de spécialité, veuillez contacter le support.</small>
                    </div>
                    <div class="form-group"><label for="email-profil-med">Email Professionnel : <span class="text-danger">*</span></label><input type="email" id="email-profil-med" name="email" class="form-control <?= isset($form_errors_info_profil_med['email']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_info_profil_med['email'] ?? '') ?>" required><small class="form-error-message"><?= htmlspecialchars($form_errors_info_profil_med['email'] ?? '') ?></small></div>
                    <div class="form-group"><label for="telephone-profil-med">Téléphone Professionnel : <span class="text-danger">*</span></label><input type="tel" id="telephone-profil-med" name="telephone" class="form-control <?= isset($form_errors_info_profil_med['telephone']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_info_profil_med['telephone'] ?? '') ?>" required pattern="[0-9\s\-\+()]{10,20}"><small class="form-error-message"><?= htmlspecialchars($form_errors_info_profil_med['telephone'] ?? '') ?></small></div>
                    <div class="form-group"><label for="adresse-profil-med">Adresse du Cabinet : <span class="text-danger">*</span></label><input type="text" id="adresse-profil-med" name="adresse" class="form-control <?= isset($form_errors_info_profil_med['adresse']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_info_profil_med['adresse'] ?? '') ?>" required><small class="form-error-message"><?= htmlspecialchars($form_errors_info_profil_med['adresse'] ?? '') ?></small></div>
                     <div class="form-group">
                        <label for="horaires-profil-med">Description des Horaires (Affichage public) :</label>
                        <textarea id="horaires-profil-med" name="horaires" class="form-control <?= isset($form_errors_info_profil_med['horaires']) ? 'input-error' : '' ?>" rows="4" placeholder="Ex: Lundi - Vendredi : 9h00 - 12h00 et 14h00 - 18h00. Samedi : 9h00 - 12h00 (sur RDV)."><?= htmlspecialchars($form_data_info_profil_med['horaires'] ?? '') ?></textarea>
                        <small class="form-note">Ces horaires sont indicatifs. Gérez vos créneaux de rendez-vous précis via "Mes Disponibilités".</small>
                        <small class="form-error-message"><?= htmlspecialchars($form_errors_info_profil_med['horaires'] ?? '') ?></small>
                    </div>
                    
                    <h3 class="form-subtitle" style="margin-top: var(--spacing-lg); margin-bottom: var(--spacing-md);"><i class="fas fa-map-marked-alt icon-left"></i>Localisation du Cabinet</h3>
                    <p class="form-note">Cliquez sur la carte pour placer un marqueur à l'emplacement exact de votre cabinet, ou utilisez la barre de recherche sur la carte. Les coordonnées seront automatiquement remplies.</p>
                    <div id="mapPickLocation"></div> 
                    
                    <input type="hidden" id="latitude-profil-med-hidden" name="latitude" value="<?= htmlspecialchars($form_data_info_profil_med['latitude'] ?? '') ?>">
                    <input type="hidden" id="longitude-profil-med-hidden" name="longitude" value="<?= htmlspecialchars($form_data_info_profil_med['longitude'] ?? '') ?>">
                    
                    <div class="form-group" style="margin-top: var(--spacing-sm);">
                        <label>Coordonnées sélectionnées :</label>
                        <span id="selectedCoordsDisplay" class="text-muted">
                            Lat: <?= htmlspecialchars($form_data_info_profil_med['latitude'] ?? 'Non définies') ?>, 
                            Lng: <?= htmlspecialchars($form_data_info_profil_med['longitude'] ?? 'Non définies') ?>
                        </span>
                         <small class="form-error-message"><?= htmlspecialchars($form_errors_info_profil_med['latitude'] ?? ($form_errors_info_profil_med['longitude'] ?? '')) ?></small>
                    </div>

                    <div class="form-group">
                        <button type="button" id="findCoordsButton" class="btn btn-sm secondary-action">
                            <i class="fas fa-search-location icon-left"></i>Comment trouver mes coordonnées ?
                        </button>
                        <div id="coordsHelper" style="display:none; margin-top:10px; padding:10px; background-color:#f0f0f0; border-radius:4px; border:1px solid #ddd;">
                            <p style="margin-bottom:5px; font-weight:bold;">Pour trouver les coordonnées de votre cabinet :</p>
                            <ol style="margin-left:20px; font-size:0.9em; line-height:1.5;">
                                <li>Ouvrez <a href="https://www.google.com/maps" target="_blank" rel="noopener noreferrer" class="link-emphasis">Google Maps</a> dans un nouvel onglet.</li>
                                <li>Recherchez l'adresse exacte de votre cabinet.</li>
                                <li>Une fois l'emplacement trouvé, faites un clic droit sur le marqueur rouge (ou l'emplacement précis) sur la carte.</li>
                                <li>Un petit menu contextuel apparaîtra. La première ligne affichera les coordonnées (ex: `33.573110, -7.589843`).</li>
                                <li>Cliquez sur ces coordonnées pour les copier dans votre presse-papiers.</li>
                                <li>Collez la première partie (latitude) dans le champ "Latitude" et la deuxième partie (longitude) dans le champ "Longitude" ci-dessus (via la carte).</li>
                            </ol>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: var(--spacing-lg);">
                        <label>Document Justificatif Soumis :</label>
                        <?php if (!empty($medecin_db_data_profil['document_justificatif'])): ?>
                            <p><a href="../<?= htmlspecialchars($medecin_db_data_profil['document_justificatif']) ?>" target="_blank" class="link-emphasis"><i class="fas fa-file-pdf icon-left"></i>Voir mon document (<?= basename(htmlspecialchars($medecin_db_data_profil['document_justificatif'])) ?>)</a></p>
                        <?php else: ?><p class="text-muted">Aucun document justificatif n'a été soumis ou trouvé.</p><?php endif; ?>
                         <small class="form-note">La modification du document justificatif se fait uniquement via contact avec l'administration pour revérification.</small>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn primary-action"><i class="fas fa-save icon-left"></i>Enregistrer les Informations</button>
                    </div>
                </form>
            </section>

            <section id="changePasswordSectionMedecin" class="profile-section card">
                <h2 class="section-title"><i class="fas fa-key icon-left"></i>Changer mon mot de passe</h2>
                <form id="changePasswordFormMedecin" action="changer_motdepasse.php" method="POST" class="user-form">
                    <?= csrf_input_field() ?>
                    <input type="hidden" name="form_origin_pass" value="profil_medecin.php#changePasswordSectionMedecin">
                    <?php if (isset($form_errors_pass_profil_med['_general'])): ?><p class="alert alert-danger"><?= htmlspecialchars($form_errors_pass_profil_med['_general']) ?></p><?php endif; ?>
                    
                    <div class="form-group"><label for="ancien_motdepasse-med">Ancien mot de passe : <span class="text-danger">*</span></label><input type="password" id="ancien_motdepasse-med" name="ancien_motdepasse" class="form-control <?= isset($form_errors_pass_profil_med['ancien_motdepasse']) ? 'input-error' : '' ?>" required><small class="form-error-message"><?= htmlspecialchars($form_errors_pass_profil_med['ancien_motdepasse'] ?? '') ?></small></div>
                    <div class="form-group"><label for="nouveau_motdepasse-med">Nouveau mot de passe : <span class="text-danger">*</span></label><input type="password" id="nouveau_motdepasse-med" name="nouveau_motdepasse" class="form-control <?= isset($form_errors_pass_profil_med['nouveau_motdepasse']) ? 'input-error' : '' ?>" required minlength="8"><small class="form-note">Minimum 8 caractères.</small><small class="form-error-message"><?= htmlspecialchars($form_errors_pass_profil_med['nouveau_motdepasse'] ?? '') ?></small></div>
                    <div class="form-group"><label for="confirmer_motdepasse-med">Confirmer le nouveau : <span class="text-danger">*</span></label><input type="password" id="confirmer_motdepasse-med" name="confirmer_motdepasse" class="form-control <?= isset($form_errors_pass_profil_med['confirmer_motdepasse']) ? 'input-error' : '' ?>" required><small class="form-field-feedback password-feedback" id="feedback-profil-password-med"></small><small class="form-error-message error-message-display"><?= htmlspecialchars($form_errors_pass_profil_med['confirmer_motdepasse'] ?? '') ?></small></div>
                    
                    <div class="form-actions"><button type="submit" class="btn primary-action"><i class="fas fa-lock icon-left"></i>Changer le mot de passe</button></div>
                </form>
            </section>
        </div> 
    </div> 
</main>

<footer class="site-footer">
   <div class="container"><p class="copyright-text text-center">© <span id="footer-year"></span> SANTE TV Plus. Tous droits réservés.</p></div>
</footer>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-geosearch@3.11.0/dist/geosearch.umd.js"></script>
<script src="../assets/js/script.js"></script> 
<script>
    const initialProfileCoords = {
        lat: <?= json_encode(isset($form_data_info_profil_med['latitude']) && is_numeric($form_data_info_profil_med['latitude']) ? (float)$form_data_info_profil_med['latitude'] : null, JSON_NUMERIC_CHECK) ?>,
        lng: <?= json_encode(isset($form_data_info_profil_med['longitude']) && is_numeric($form_data_info_profil_med['longitude']) ? (float)$form_data_info_profil_med['longitude'] : null, JSON_NUMERIC_CHECK) ?>
    };
</script>
</body>
</html>