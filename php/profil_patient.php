<?php
session_start();
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 

// 1. Vérification de l'authentification
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'patient') {
    $_SESSION['flash_message_login'] = "Accès non autorisé. Veuillez vous connecter."; // Message pour la page de connexion
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/connexion.php'); 
    exit;
}
$patient_id = $_SESSION['utilisateur_id'];

// 2. Récupérer les infos actuelles du patient
$stmt_patient_db = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt_patient_db->execute([$patient_id]);
$patient_db_data = $stmt_patient_db->fetch(PDO::FETCH_ASSOC);

if (!$patient_db_data) {
    $_SESSION['flash_message_login'] = "Erreur: Votre profil patient est introuvable.";
    $_SESSION['flash_type_login'] = "error";
    session_unset(); 
    session_destroy();
    header('Location: ../pages/connexion.php');
    exit;
}
$nom_patient_display_profil = htmlspecialchars(($patient_db_data['prenom'] ?? '') . ' ' . ($patient_db_data['nom'] ?? 'Patient'));

// 3. Récupérer les données de session pour les formulaires
$form_data_info_profil_patient = $_SESSION['form_data_maj_profil_patient'] ?? $patient_db_data; 
$form_errors_info_profil_patient = $_SESSION['form_errors_maj_profil_patient'] ?? [];
unset($_SESSION['form_data_maj_profil_patient'], $_SESSION['form_errors_maj_profil_patient']);

$form_errors_pass_profil_patient = $_SESSION['form_errors_change_pass_patient'] ?? [];
unset($_SESSION['form_errors_change_pass_patient']);

// 4. Message flash général
$flash_message_profil_page = $_SESSION['flash_message'] ?? null;
$flash_type_profil_page = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// 5. Vérifier si la colonne 'telephone' existe
$has_telephone_column_profil = false; 
try {
    $table_patients_exists_check = $pdo->query("SHOW TABLES LIKE 'patients'")->rowCount() > 0;
    if ($table_patients_exists_check) {
        $colonnes_patient_profil = $pdo->query("DESCRIBE patients")->fetchAll(PDO::FETCH_COLUMN);
        $has_telephone_column_profil = in_array('telephone', $colonnes_patient_profil);
    }
} catch (PDOException $e) {
    error_log("Erreur DESCRIBE patients (profil_patient.php): " . $e->getMessage());
}

// 6. Générer les tokens CSRF
$csrf_token_info_profil_patient = generate_csrf_token(); 
$csrf_token_pass_profil_patient = $csrf_token_info_profil_patient; 

// Badges de navigation
$stmt_rdv_nav_profil = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE patient_id = :id AND ((date_rdv > CURDATE()) OR (date_rdv = CURDATE() AND heure_rdv >= CURTIME())) AND statut IN ('en attente', 'confirmé')");
$stmt_rdv_nav_profil->execute([':id' => $patient_id]);
$nb_rdv_nav_profil = $stmt_rdv_nav_profil->fetchColumn();

$nb_notif_nav_profil = 0;
if ($table_patients_exists_check && $pdo->query("SHOW TABLES LIKE 'notifications_patients'")->rowCount() > 0) { // Vérifier aussi table notif
    $stmt_notif_nav_profil = $pdo->prepare("SELECT COUNT(*) FROM notifications_patients WHERE patient_id = :id AND lu = 0");
    $stmt_notif_nav_profil->execute([':id' => $patient_id]);
    $nb_notif_nav_profil = $stmt_notif_nav_profil->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - <?= $nom_patient_display_profil ?> - SANTE TV</title>
    <meta name="description" content="Gérez vos informations personnelles, votre photo de profil et votre mot de passe sur votre espace patient SANTE TV.">
    <!-- Ce fichier est dans php/, donc assets/ est à ../assets/ -->
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.tiny.cloud/1/4amskh9mj3cii8jraizm25oi6k5kvplfgmzr48dm2b52fgj7/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body class="profile-page user-dashboard-page"> 

<header class="site-header">
    <div class="container">
        <!-- Lien vers index.php à la racine -->
        <div class="logo-branding"><a href="../index.php" title="Accueil SANTE TV"><img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img"><span class="site-title">SANTE TV</span></a></div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav"><span></span><span></span><span></span></button>
        <nav class="main-navigation" id="main-nav">
             <ul>
                <li><a href="dashboard_patient.php" class="nav-link">Mon Espace</a></li>
                <li><a href="../pages/docteurs.php" class="nav-link">Trouver un Médecin</a></li>
                <li><a href="mes_rendez_vous_patient.php" class="nav-link">Mes Rendez-vous
                    <?php if($nb_rdv_nav_profil > 0): ?><span class="badge-notification"><?= $nb_rdv_nav_profil ?></span><?php endif; ?>
                </a></li>
                <li><a href="messages_patient.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'messages_patient.php') ? 'active' : ''; ?>"> Notifications
                    <?php if(isset($nombre_notifications_non_lues) && $nombre_notifications_non_lues > 0): // Adaptez le nom de la variable si besoin ?>
                    <span class="badge-notification"><?= $nombre_notifications_non_lues ?></span>
                    <?php endif; ?>
                    </a>
                </li>
                <li><a href="profil_patient.php" class="nav-link active">Mon Profil</a></li>
                <li><a href="deconnexion.php" class="nav-link btn btn-sm btn-danger">Déconnexion</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content section-padding">
    <div class="container">
        <div class="page-header">
            <h1 class="page-main-title">Mon Profil Personnel</h1>
             <a href="dashboard_patient.php" class="btn btn-sm secondary-action">← Retour à Mon Espace</a>
        </div>

        <?php if ($flash_message_profil_page): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_profil_page) ?> alert-dismissible" id="profilePageFlashMessage">
                <?= htmlspecialchars($flash_message_profil_page) ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button>
            </div>
        <?php endif; ?>

        <div class="profile-content-grid"> 
            <section id="infosPersonnellesSection" class="profile-section card">
                <h2 class="section-title"><i class="fas fa-user-edit icon-left"></i>Mes Informations Personnelles</h2>
                <form id="profileUpdateFormPatient" action="maj_profil.php" method="POST" enctype="multipart/form-data" class="user-form">
                    <?= csrf_input_field() ?>
                    <input type="hidden" name="form_origin_profil" value="profil_patient.php#infosPersonnellesSection">
                    <?php if (isset($form_errors_info_profil_patient['_general'])): ?><p class="alert alert-danger"><?= htmlspecialchars($form_errors_info_profil_patient['_general']) ?></p><?php endif; ?>

                    <div class="current-profile-picture-container text-center">
                        <img src="<?= $form_data_info_profil_patient['photo'] ? '../' . htmlspecialchars($form_data_info_profil_patient['photo']) : '../assets/images/placeholder-patient.png' ?>" 
                             alt="Photo de profil actuelle" class="profile-photo-display" id="currentProfilePicPatient" 
                             onclick="document.getElementById('photoInputPatient').click();" title="Cliquez pour changer la photo">
                        <!-- STYLE: display inline -->
                        <img src="#" alt="Aperçu nouvelle photo" id="photoPreviewPatient" class="profile-photo-display" style="display:none;">
                    </div>
                    <div class="form-group">
                        <!-- STYLE: display, margin, cursor inline -->
                        <label for="photoInputPatient" class="btn btn-sm secondary-action" style="display:table; margin: 0 auto 1rem auto; cursor:pointer;">
                            <i class="fas fa-camera icon-left"></i>Choisir une nouvelle photo
                        </label>
                        <!-- STYLE: display inline -->
                        <input type="file" id="photoInputPatient" name="photo" class="form-control-file <?= isset($form_errors_info_profil_patient['photo']) ? 'input-error' : '' ?>" accept="image/jpeg,image/png,image/gif" style="display:none;" onchange="previewProfilePhoto(event, 'photoPreviewPatient', 'currentProfilePicPatient')">
                        <small class="form-note text-center">Formats: JPG, PNG, GIF. Max 2MB.</small>
                        <small class="form-error-message"><?= htmlspecialchars($form_errors_info_profil_patient['photo'] ?? '') ?></small>
                    </div>

                    <div class="form-group"><label for="nom-profil-patient">Nom  <span class="text-danger">*</span></label><input type="text" id="nom-profil-patient" name="nom" class="form-control <?= isset($form_errors_info_profil_patient['nom']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_info_profil_patient['nom'] ?? '') ?>" required><small class="form-error-message"><?= htmlspecialchars($form_errors_info_profil_patient['nom'] ?? '') ?></small></div>
                    <div class="form-group"><label for="prenom-profil-patient">Prénom  <span class="text-danger">*</span></label><input type="text" id="prenom-profil-patient" name="prenom" class="form-control <?= isset($form_errors_info_profil_patient['prenom']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_info_profil_patient['prenom'] ?? '') ?>" required><small class="form-error-message"><?= htmlspecialchars($form_errors_info_profil_patient['prenom'] ?? '') ?></small></div>
                    <div class="form-group"><label for="email-profil-patient">Email  <span class="text-danger">*</span></label><input type="email" id="email-profil-patient" name="email" class="form-control <?= isset($form_errors_info_profil_patient['email']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_info_profil_patient['email'] ?? '') ?>" required><small class="form-error-message"><?= htmlspecialchars($form_errors_info_profil_patient['email'] ?? '') ?></small></div>
                    
                    <?php if ($has_telephone_column_profil): ?>
                    <div class="form-group"><label for="telephone-profil-patient">Téléphone </label><input type="tel" id="telephone-profil-patient" name="telephone" class="form-control <?= isset($form_errors_info_profil_patient['telephone']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_info_profil_patient['telephone'] ?? '') ?>" pattern="[0-9\s\-\+()]{8,20}"><small class="form-error-message"><?= htmlspecialchars($form_errors_info_profil_patient['telephone'] ?? '') ?></small></div>
                    <?php endif; ?>
                    
                    <div class="form-group"><label for="adresse-profil-patient">Adresse </label><input type="text" id="adresse-profil-patient" name="adresse" class="form-control <?= isset($form_errors_info_profil_patient['adresse']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_info_profil_patient['adresse'] ?? '') ?>"><small class="form-error-message"><?= htmlspecialchars($form_errors_info_profil_patient['adresse'] ?? '') ?></small></div>
                    <div class="form-group"><label for="date_naissance-profil-patient">Date de naissance  <span class="text-danger">*</span></label><input type="date" id="date_naissance-profil-patient" name="date_naissance" class="form-control <?= isset($form_errors_info_profil_patient['date_naissance']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_info_profil_patient['date_naissance'] ?? '') ?>" required max="<?= date('Y-m-d', strtotime('-1 day')) ?>"><small class="form-error-message"><?= htmlspecialchars($form_errors_info_profil_patient['date_naissance'] ?? '') ?></small></div>
                    <div class="form-group"><label for="sexe-profil-patient">Sexe  <span class="text-danger">*</span></label><select id="sexe-profil-patient" name="sexe" class="form-control <?= isset($form_errors_info_profil_patient['sexe']) ? 'input-error' : '' ?>" required><option value="Homme" <?= (isset($form_data_info_profil_patient['sexe']) && $form_data_info_profil_patient['sexe'] === 'Homme') ? 'selected' : '' ?>>Homme</option><option value="Femme" <?= (isset($form_data_info_profil_patient['sexe']) && $form_data_info_profil_patient['sexe'] === 'Femme') ? 'selected' : '' ?>>Femme</option></select><small class="form-error-message"><?= htmlspecialchars($form_errors_info_profil_patient['sexe'] ?? '') ?></small></div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn primary-action"><i class="fas fa-save icon-left"></i>Enregistrer les Modifications</button>
                    </div>
                </form>
            </section>

            <section id="changePasswordSection" class="profile-section card">
                <h2 class="section-title"><i class="fas fa-key icon-left"></i>Changer mon mot de passe</h2>
                <form id="changePasswordFormPatient" action="changer_motdepasse.php" method="POST" class="user-form">
                    <?= csrf_input_field() ?>
                    <input type="hidden" name="form_origin_pass" value="profil_patient.php#changePasswordSection">
                    <?php if (isset($form_errors_pass_profil_patient['_general'])): ?><p class="alert alert-danger"><?= htmlspecialchars($form_errors_pass_profil_patient['_general']) ?></p><?php endif; ?>
                    
                    <div class="form-group"><label for="ancien_motdepasse_patient">Ancien mot de passe  <span class="text-danger">*</span></label><input type="password" id="ancien_motdepasse_patient" name="ancien_motdepasse" class="form-control <?= isset($form_errors_pass_profil_patient['ancien_motdepasse']) ? 'input-error' : '' ?>" required><small class="form-error-message"><?= htmlspecialchars($form_errors_pass_profil_patient['ancien_motdepasse'] ?? '') ?></small></div>
                    <div class="form-group"><label for="nouveau_motdepasse_patient">Nouveau mot de passe  <span class="text-danger">*</span></label><input type="password" id="nouveau_motdepasse_patient" name="nouveau_motdepasse" class="form-control <?= isset($form_errors_pass_profil_patient['nouveau_motdepasse']) ? 'input-error' : '' ?>" required minlength="8"><small class="form-note">Minimum 8 caractères.</small><small class="form-error-message"><?= htmlspecialchars($form_errors_pass_profil_patient['nouveau_motdepasse'] ?? '') ?></small></div>
                    <div class="form-group"><label for="confirmer_motdepasse_patient">Confirmer le nouveau  <span class="text-danger">*</span></label><input type="password" id="confirmer_motdepasse_patient" name="confirmer_motdepasse" class="form-control <?= isset($form_errors_pass_profil_patient['confirmer_motdepasse']) ? 'input-error' : '' ?>" required><small class="form-field-feedback password-feedback" id="feedback-profil-password-patient"></small><small class="form-error-message"><?= htmlspecialchars($form_errors_pass_profil_patient['confirmer_motdepasse'] ?? '') ?></small></div>
                    
                    <div class="form-actions"><button type="submit" class="btn primary-action"><i class="fas fa-lock icon-left"></i>Changer le mot de passe</button></div>
                </form>
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