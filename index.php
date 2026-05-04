<?php
session_start();
require_once 'php/db.php';
require_once 'php/utils/app_settings.php'; 
require_once 'php/utils/csrf_utils.php';

if (defined('MAINTENANCE_MODE_ENABLED') && MAINTENANCE_MODE_ENABLED === true) {
    if (!isset($_SESSION['admin_id'])) { 
        header('HTTP/1.1 503 Service Temporarily Unavailable');
        header('Status: 503 Service Temporarily Unavailable');
        header('Retry-After: 3600'); 
        $message_maintenance = defined('MAINTENANCE_MESSAGE_DEFAULT') ? MAINTENANCE_MESSAGE_DEFAULT : 'Le site est actuellement en maintenance. Nous serons de retour bientôt.';
        echo "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><title>Maintenance - " . (defined('NOM_APPLICATION') ? htmlspecialchars(NOM_APPLICATION) : 'Site Web') . "</title><style>body{font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f0f2f5; color: #333; text-align: center;} .maintenance-container{padding: 30px; background-color: #fff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);}.logo img {max-height: 60px; margin-bottom: 20px;}</style></head><body><div class='maintenance-container'><div class='logo'><img src='assets/images/logo1.png' alt='Logo ".(defined('NOM_APPLICATION') ? htmlspecialchars(NOM_APPLICATION) : 'Site Web')."'></div><h1>Site en Maintenance</h1><p>" . htmlspecialchars($message_maintenance) . "</p></div></body></html>";
        exit();
    }
}

$apercu_medecins = [];
try {
    $nombre_medecins_accueil = defined('NOMBRE_MEDECINS_ACCUEIL_DEFAULT') ? NOMBRE_MEDECINS_ACCUEIL_DEFAULT : 4;
    if ($nombre_medecins_accueil <= 0) $nombre_medecins_accueil = 1;

    $stmt_apercu_medecins = $pdo->prepare(
        "SELECT id, nom, prenom, specialite, photo, horaires, adresse, latitude, longitude, telephone
         FROM medecins
         WHERE valide = 1 AND photo IS NOT NULL AND photo != ''
         ORDER BY RAND()
         LIMIT :limit_val"
    );
    $stmt_apercu_medecins->bindValue(':limit_val', (int)$nombre_medecins_accueil, PDO::PARAM_INT);
    $stmt_apercu_medecins->execute();
    $apercu_medecins = $stmt_apercu_medecins->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur récupération aperçu médecins (index.php): " . $e->getMessage());
}

$form_data_med_insc_modal = $_SESSION['form_data_medecin_modal'] ?? [];
$form_errors_med_insc_modal = $_SESSION['form_errors_medecin_modal'] ?? [];
unset($_SESSION['form_data_medecin_modal'], $_SESSION['form_errors_medecin_modal']);

$form_data_patient_insc_modal = $_SESSION['form_data_patient_modal'] ?? [];
$form_errors_patient_insc_modal = $_SESSION['form_errors_patient_modal'] ?? [];
unset($_SESSION['form_data_patient_modal'], $_SESSION['form_errors_patient_modal']);

$form_data_connexion_modal = $_SESSION['form_data_connexion_modal'] ?? [];
$form_errors_connexion_modal = $_SESSION['form_errors_connexion_modal'] ?? [];
unset($_SESSION['form_data_connexion_modal'], $_SESSION['form_errors_connexion_modal']);

$form_data_forgot_pass_modal = $_SESSION['form_data_forgot_pass_modal'] ?? [];
$form_errors_forgot_pass_modal = $_SESSION['form_errors_forgot_pass_modal'] ?? [];
unset($_SESSION['form_data_forgot_pass_modal'], $_SESSION['form_errors_forgot_pass_modal']);

$flash_message_index = $_SESSION['flash_message'] ?? null;
$flash_type_index = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$csrf_token = generate_csrf_token();

$specialites_liste_complete = [
    "Cardiologie", "Chirurgie cardiaque", "Chirurgie générale", "Chirurgien orthopédique",
    "Dermatologie", "Endocrinologie", "Gastro-entérologue", "Gériatre", "Interniste",
    "Médecine générale", "Néphrologue", "Neurochirurgien", "Oncologue", "Ophtalmologie",
    "ORL", "Pédiatrie", "Pneumologue", "Psychiatrie", "Radiologue", "Rhumatologue", "Autre"
];

$specialites_descriptions = [
    "Cardiologie" => "Spécialité médicale étudiant le cœur et ses affections, ainsi que les problèmes vasculaires.",
    "Chirurgie cardiaque" => "Chirurgie du cœur et des gros vaisseaux par un chirurgien cardiaque.",
    "Chirurgie générale" => "Domaine chirurgical large incluant diverses opérations, notamment abdominales.",
    "Chirurgien orthopédique" => "Spécialiste des maladies, traumatismes de l'appareil locomoteur (os, articulations, ligaments).",
    "Dermatologie" => "Diagnostic et traitement des maladies de la peau, des cheveux, des ongles et des muqueuses.",
    "Endocrinologie" => "Étude des hormones, du métabolisme (diabète, thyroïde) et des maladies des glandes endocrines.",
    "Gastro-entérologue" => "Prise en charge des maladies du tube digestif (œsophage, estomac, intestin), foie, pancréas.",
    "Gériatre" => "Médecine spécialisée dans les soins aux personnes âgées et les maladies liées au vieillissement.",
    "Interniste" => "Spécialiste du diagnostic complexe, des maladies systémiques et de la prise en charge globale.",
    "Médecine générale" => "Soins de premier recours, suivi global et prévention pour tous les âges.",
    "Néphrologue" => "Prévention, diagnostic et traitement des maladies des reins.",
    "Neurochirurgien" => "Chirurgie du système nerveux central (cerveau, moelle épinière) et périphérique.",
    "Oncologue" => "Diagnostic et traitement des cancers (oncologie médicale, radiothérapie).",
    "Ophtalmologie" => "Traitement des maladies de l'œil et correction des troubles de la vision.",
    "ORL" => "Spécialiste des maladies de l'oreille, du nez, de la gorge, et de la chirurgie cervico-faciale.",
    "Pédiatrie" => "Médecine des enfants, de la naissance à l'adolescence.",
    "Pneumologue" => "Traitement des maladies des poumons, des bronches et de la plèvre.",
    "Psychiatrie" => "Diagnostic, traitement et prévention des maladies mentales et des troubles émotionnels.",
    "Radiologue" => "Utilisation des techniques d'imagerie médicale (radio, scanner, IRM, écho) pour diagnostic et traitement.",
    "Rhumatologue" => "Prise en charge des douleurs et maladies des os, articulations, muscles et tendons.",
    "Autre" => "Pour les médecins dont la spécialité n'est pas listée ou pour des pratiques alternatives/complémentaires spécifiques."
];

$nom_application_display = defined('NOM_APPLICATION') ? htmlspecialchars(NOM_APPLICATION) : 'SANTE TV';
$email_contact_display = defined('EMAIL_CONTACT_PRINCIPAL') ? htmlspecialchars(EMAIL_CONTACT_PRINCIPAL) : 'contact@example.com';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $nom_application_display ?> - Prise de Rendez-vous Médicaux en Ligne</title>
    <meta name="description" content="<?= $nom_application_display ?> : Prenez rendez-vous facilement avec des médecins spécialistes. Trouvez un praticien et réservez votre consultation en ligne.">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body class="body-index">

<header class="site-header">
    <div class="container">
        <div class="logo-branding">
            <a href="index.php" title="<?= $nom_application_display ?> Accueil">
                <img src="assets/images/logo1.png" alt="<?= $nom_application_display ?> Logo" id="logo-img">
                <span class="site-title"><?= $nom_application_display ?></span>
            </a>
        </div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir/Fermer le menu" aria-expanded="false" aria-controls="main-nav">
            <span></span><span></span><span></span>
        </button>
        <nav class="main-navigation" id="main-nav">
            <ul>
                <li><a href="#accueil" class="nav-link active">ACCUEIL</a></li>
                <li><a href="#docteurs" class="nav-link">NOS MEDECINS</a></li>
                <li><a href="#" data-modal-target="#modal-form" class="nav-link">REJOIGNEZ NOUS</a></li>
                <li><a href="#apropos" class="nav-link">A PROPOS</a></li>
                <?php if (isset($_SESSION['utilisateur_id'])): ?>
                    <?php if ($_SESSION['type'] === 'patient'): ?>
                        <li><a href="php/dashboard_patient.php" class="nav-link btn-header-connect">MON ESPACE</a></li>
                    <?php elseif ($_SESSION['type'] === 'medecin'): ?>
                        <li><a href="php/espace_medecin.php" class="nav-link btn-header-connect">MON ESPACE</a></li>
                     <?php elseif ($_SESSION['type'] === 'admin'): ?>
                        <li><a href="admin/dashboard_admin.php" class="nav-link btn-header-connect">ADMIN</a></li>
                    <?php endif; ?>
                    <li><a href="php/deconnexion.php" class="nav-link" style="color: var(--color-warning);">DÉCONNEXION</a></li>
                <?php else: ?>
                    <li><a href="#" data-modal-target="#modal-connexion" class="nav-link btn-header-connect">SE CONNECTER</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<?php if ($flash_message_index): ?>
    <div class="container flash-container" style="margin-top: calc(var(--header-height) + 1rem); margin-bottom: -1rem; position:relative; z-index:1500;">
        <div class="alert alert-<?= htmlspecialchars($flash_type_index) ?> alert-dismissible">
            <?= htmlspecialchars($flash_message_index) ?>
            <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button>
        </div>
    </div>
<?php endif; ?>

<div id="modal-form" class="modal" aria-labelledby="titleModalMedecinInsc" role="dialog" aria-modal="true"> <div class="modal-content"> <button class="close-modal-button" aria-label="Fermer">×</button> <form id="inscriptionMedecinFormModal" action="php/inscription_medecin.php" method="post" enctype="multipart/form-data" class="user-form"><?= csrf_input_field() ?><input type="hidden" name="form_origin_medecin" value="index.php#modal-form"><h2 class="form-title" id="titleModalMedecinInsc">Inscription Médecin</h2><?php if (isset($form_errors_med_insc_modal['_general'])): ?><p class="alert alert-danger"><?= htmlspecialchars($form_errors_med_insc_modal['_general']) ?></p><?php endif; ?><div class="form-group"><label for="nom-med-insc-modal">Nom : <span class="text-danger">*</span></label><input type="text" id="nom-med-insc-modal" name="nom" class="form-control <?= isset($form_errors_med_insc_modal['nom']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_med_insc_modal['nom'] ?? '') ?>" required><small class="form-error-message"><?= htmlspecialchars($form_errors_med_insc_modal['nom'] ?? '') ?></small></div><div class="form-group"><label for="prenom-med-insc-modal">Prénom : <span class="text-danger">*</span></label><input type="text" id="prenom-med-insc-modal" name="prenom" class="form-control <?= isset($form_errors_med_insc_modal['prenom']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_med_insc_modal['prenom'] ?? '') ?>" required><small class="form-error-message"><?= htmlspecialchars($form_errors_med_insc_modal['prenom'] ?? '') ?></small></div><div class="form-group"><label for="specialite-med-insc-modal">Spécialité : <span class="text-danger">*</span></label><select id="specialite-med-insc-modal" name="specialite" class="form-control <?= isset($form_errors_med_insc_modal['specialite']) ? 'input-error' : '' ?>" required><option value="">Sélectionner une spécialité</option><?php foreach($specialites_liste_complete as $spec): ?><option value="<?= htmlspecialchars($spec) ?>" <?= (isset($form_data_med_insc_modal['specialite']) && $form_data_med_insc_modal['specialite'] === $spec) ? 'selected' : '' ?>><?= htmlspecialchars($spec) ?></option><?php endforeach; ?></select><small class="form-error-message"><?= htmlspecialchars($form_errors_med_insc_modal['specialite'] ?? '') ?></small></div><div class="form-group"><label for="email-med-insc-modal">Email : <span class="text-danger">*</span></label><input type="email" id="email-med-insc-modal" name="email" class="form-control <?= isset($form_errors_med_insc_modal['email']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_med_insc_modal['email'] ?? '') ?>" required><small class="form-error-message"><?= htmlspecialchars($form_errors_med_insc_modal['email'] ?? '') ?></small></div><div class="form-group"><label for="telephone-med-insc-modal">Téléphone : <span class="text-danger">*</span></label><input type="tel" id="telephone-med-insc-modal" name="telephone" class="form-control <?= isset($form_errors_med_insc_modal['telephone']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_med_insc_modal['telephone'] ?? '') ?>" required pattern="[0-9\s\-\+()]{10,20}"><small class="form-error-message"><?= htmlspecialchars($form_errors_med_insc_modal['telephone'] ?? '') ?></small></div><div class="form-group"><label for="adresse-med-insc-modal">Adresse Cabinet : <span class="text-danger">*</span></label><input type="text" id="adresse-med-insc-modal" name="adresse" class="form-control <?= isset($form_errors_med_insc_modal['adresse']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_med_insc_modal['adresse'] ?? '') ?>" required><small class="form-error-message"><?= htmlspecialchars($form_errors_med_insc_modal['adresse'] ?? '') ?></small></div><div class="form-group"><label for="mot_de_passe-med-insc-modal">Mot de passe : <span class="text-danger">*</span></label><input type="password" id="mot_de_passe-med-insc-modal" name="mot_de_passe" class="form-control <?= isset($form_errors_med_insc_modal['mot_de_passe']) ? 'input-error' : '' ?>" required minlength="8"><small class="form-note">Minimum 8 caractères.</small><small class="form-error-message"><?= htmlspecialchars($form_errors_med_insc_modal['mot_de_passe'] ?? '') ?></small></div><div class="form-group"><label for="confirmer_mot_de_passe-med-insc-modal">Confirmer : <span class="text-danger">*</span></label><input type="password" id="confirmer_mot_de_passe-med-insc-modal" name="confirmer_mot_de_passe" class="form-control <?= isset($form_errors_med_insc_modal['confirmer_mot_de_passe']) ? 'input-error' : '' ?>" required><small class="form-field-feedback password-feedback"></small> <small class="form-error-message error-message-display"><?= htmlspecialchars($form_errors_med_insc_modal['confirmer_mot_de_passe'] ?? '') ?></small></div><div class="form-group"><label for="documents-med-insc-modal">Documents justificatifs : <span class="text-danger">*</span></label><input type="file" id="documents-med-insc-modal" name="documents" class="form-control-file <?= isset($form_errors_med_insc_modal['documents']) ? 'input-error' : '' ?>" accept=".pdf,.jpg,.jpeg,.png" required><small class="form-note">Formats: PDF, JPG, PNG. Max 5MB.</small><small class="form-error-message"><?= htmlspecialchars($form_errors_med_insc_modal['documents'] ?? '') ?></small></div><div class="form-actions"><button type="submit" class="submit-button primary-action">Demander l'inscription</button></div></form> </div> </div>
<div id="modal-inscription" class="modal" aria-labelledby="titleModalPatientInsc" role="dialog" aria-modal="true"> <div class="modal-content"> <button class="close-modal-button" aria-label="Fermer">×</button> <form id="inscriptionPatientFormModal" action="php/inscription_patient.php" method="post" class="user-form"><?= csrf_input_field() ?><input type="hidden" name="form_origin" value="index.php#modal-inscription"><h2 class="form-title" id="titleModalPatientInsc">Inscription Patient</h2><?php if (isset($form_errors_patient_insc_modal['_general'])): ?><p class="alert alert-danger"><?= htmlspecialchars($form_errors_patient_insc_modal['_general']) ?></p><?php endif; ?><div class="form-group"><label for="nom-patient-insc-modal">Nom : <span class="text-danger">*</span></label><input type="text" id="nom-patient-insc-modal" name="nom" class="form-control <?= isset($form_errors_patient_insc_modal['nom']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_patient_insc_modal['nom'] ?? '') ?>" required><small class="form-error-message"><?= htmlspecialchars($form_errors_patient_insc_modal['nom'] ?? '') ?></small></div><div class="form-group"><label for="prenom-patient-insc-modal">Prénom : <span class="text-danger">*</span></label><input type="text" id="prenom-patient-insc-modal" name="prenom" class="form-control <?= isset($form_errors_patient_insc_modal['prenom']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_patient_insc_modal['prenom'] ?? '') ?>" required><small class="form-error-message"><?= htmlspecialchars($form_errors_patient_insc_modal['prenom'] ?? '') ?></small></div><div class="form-group"><label for="email-patient-insc-modal">Email : <span class="text-danger">*</span></label><input type="email" id="email-patient-insc-modal" name="email" class="form-control <?= isset($form_errors_patient_insc_modal['email']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_patient_insc_modal['email'] ?? '') ?>" required><small class="form-error-message"><?= htmlspecialchars($form_errors_patient_insc_modal['email'] ?? '') ?></small></div><div class="form-group"><label for="adresse-patient-insc-modal">Adresse :</label><input type="text" id="adresse-patient-insc-modal" name="adresse" class="form-control <?= isset($form_errors_patient_insc_modal['adresse']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_patient_insc_modal['adresse'] ?? '') ?>"><small class="form-error-message"><?= htmlspecialchars($form_errors_patient_insc_modal['adresse'] ?? '') ?></small></div><div class="form-group"><label for="date_naissance-patient-insc-modal">Date de naissance : <span class="text-danger">*</span></label><input type="date" id="date_naissance-patient-insc-modal" name="date_naissance" class="form-control <?= isset($form_errors_patient_insc_modal['date_naissance']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_patient_insc_modal['date_naissance'] ?? '') ?>" required max="<?= date('Y-m-d', strtotime('-1 day')) ?>"><small class="form-error-message"><?= htmlspecialchars($form_errors_patient_insc_modal['date_naissance'] ?? '') ?></small></div><div class="form-group"><label for="sexe-patient-insc-modal">Sexe : <span class="text-danger">*</span></label><select id="sexe-patient-insc-modal" name="sexe" class="form-control <?= isset($form_errors_patient_insc_modal['sexe']) ? 'input-error' : '' ?>" required><option value="">Sélectionner</option><option value="Homme" <?= (isset($form_data_patient_insc_modal['sexe']) && $form_data_patient_insc_modal['sexe'] === 'Homme') ? 'selected' : '' ?>>Homme</option><option value="Femme" <?= (isset($form_data_patient_insc_modal['sexe']) && $form_data_patient_insc_modal['sexe'] === 'Femme') ? 'selected' : '' ?>>Femme</option></select><small class="form-error-message"><?= htmlspecialchars($form_errors_patient_insc_modal['sexe'] ?? '') ?></small></div><div class="form-group"><label for="password-patient-insc-modal">Mot de passe : <span class="text-danger">*</span></label><input type="password" id="password-patient-insc-modal" name="mot_de_passe" class="form-control <?= isset($form_errors_patient_insc_modal['mot_de_passe']) ? 'input-error' : '' ?>" required minlength="8"><small class="form-note">Minimum 8 caractères.</small><small class="form-error-message"><?= htmlspecialchars($form_errors_patient_insc_modal['mot_de_passe'] ?? '') ?></small></div><div class="form-group"><label for="confirm-password-patient-insc-modal">Confirmer : <span class="text-danger">*</span></label><input type="password" id="confirm-password-patient-insc-modal" name="confirm_mot_de_passe" class="form-control <?= isset($form_errors_patient_insc_modal['confirm_mot_de_passe']) ? 'input-error' : '' ?>" required><small class="form-field-feedback password-feedback"></small><small class="form-error-message error-message-display"><?= htmlspecialchars($form_errors_patient_insc_modal['confirm_mot_de_passe'] ?? '') ?></small></div><div class="form-actions"><button type="submit" class="submit-button primary-action">S'inscrire</button></div><p class="form-switch-prompt">Déjà un compte ? <a href="#" class="switch-modal" data-target-modal="#modal-connexion">Connectez-vous</a></p></form> </div> </div>
<div id="modal-connexion" class="modal" aria-labelledby="titleModalConnexion" role="dialog" aria-modal="true"> <div class="modal-content"> <button class="close-modal-button" aria-label="Fermer">×</button> <form id="connexionFormModal" action="php/connexion.php" method="post" class="user-form"><?= csrf_input_field() ?><input type="hidden" name="form_origin_connexion" value="index.php#modal-connexion"><h2 class="form-title" id="titleModalConnexion">Connexion</h2><?php if (isset($form_errors_connexion_modal['_general'])): ?><p class="alert alert-danger"><?= htmlspecialchars($form_errors_connexion_modal['_general']) ?></p><?php endif; ?><div class="form-group"><label for="type_utilisateur_connexion_modal">Je suis : <span class="text-danger">*</span></label><select name="type_utilisateur" id="type_utilisateur_connexion_modal" class="form-control <?= isset($form_errors_connexion_modal['type_utilisateur']) ? 'input-error' : '' ?>" required><option value="">-- Sélectionner --</option><option value="patient" <?= (isset($form_data_connexion_modal['type_utilisateur']) && $form_data_connexion_modal['type_utilisateur'] === 'patient') ? 'selected' : '' ?>>Patient</option><option value="medecin" <?= (isset($form_data_connexion_modal['type_utilisateur']) && $form_data_connexion_modal['type_utilisateur'] === 'medecin') ? 'selected' : '' ?>>Médecin</option></select><small class="form-error-message"><?= htmlspecialchars($form_errors_connexion_modal['type_utilisateur'] ?? '') ?></small></div><div class="form-group"><label for="email-connexion-modal">Email : <span class="text-danger">*</span></label><input type="email" id="email-connexion-modal" name="email" class="form-control <?= isset($form_errors_connexion_modal['email']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_connexion_modal['email'] ?? '') ?>" required><small class="form-error-message"><?= htmlspecialchars($form_errors_connexion_modal['email'] ?? '') ?></small></div><div class="form-group"><label for="password-connexion-modal">Mot de passe : <span class="text-danger">*</span></label><input type="password" id="password-connexion-modal" name="mot_de_passe" class="form-control <?= isset($form_errors_connexion_modal['mot_de_passe']) ? 'input-error' : '' ?>" required><small class="form-error-message"><?= htmlspecialchars($form_errors_connexion_modal['mot_de_passe'] ?? '') ?></small></div><div class="form-actions"><button type="submit" class="submit-button primary-action">Se connecter</button></div><p class="form-switch-prompt">Pas de compte patient ? <a href="#" class="switch-modal" data-target-modal="#modal-inscription">Inscrivez-vous</a></p><p class="text-center" style="margin-top: 0.75rem; font-size: 0.85rem;"><a href="#" data-modal-target="#modal-forgot-password" class="link-discret switch-modal">Mot de passe oublié ?</a></p></form> </div> </div>
<div id="modal-forgot-password" class="modal" aria-labelledby="titleModalForgotPassword" role="dialog" aria-modal="true"> <div class="modal-content"> <button class="close-modal-button" aria-label="Fermer">×</button> <form id="forgotPasswordFormModal" action="php/request_password_reset.php" method="post" class="user-form"><?= csrf_input_field() ?><input type="hidden" name="form_origin_forgot_password" value="index.php#modal-forgot-password"><h2 class="form-title" id="titleModalForgotPassword">Mot de Passe Oublié</h2><p class="text-center text-muted" style="margin-bottom:1.5rem; font-size:0.9em;">Entrez votre email et type de compte pour recevoir un lien de réinitialisation.</p><?php if (isset($_SESSION['flash_message_forgot_pass_modal'])): ?><div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type_forgot_pass_modal']) ?> alert-dismissible"><?= $_SESSION['flash_message_forgot_pass_modal'] ?><button type="button" class="close-alert" data-dismiss="alert">×</button></div><?php unset($_SESSION['flash_message_forgot_pass_modal'], $_SESSION['flash_type_forgot_pass_modal']); ?><?php endif; ?><?php if (isset($form_errors_forgot_pass_modal['_general'])): ?><p class="alert alert-danger"><?= htmlspecialchars($form_errors_forgot_pass_modal['_general']) ?></p><?php endif; ?><div class="form-group"><label for="email_forgot_password_modal">Email : <span class="text-danger">*</span></label><input type="email" id="email_forgot_password_modal" name="email" class="form-control <?= isset($form_errors_forgot_pass_modal['email']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($form_data_forgot_pass_modal['email'] ?? '') ?>" required><small class="form-error-message"><?= htmlspecialchars($form_errors_forgot_pass_modal['email'] ?? '') ?></small></div><div class="form-group"><label for="type_utilisateur_forgot_password_modal">Je suis : <span class="text-danger">*</span></label><select name="type_utilisateur" id="type_utilisateur_forgot_password_modal" class="form-control <?= isset($form_errors_forgot_pass_modal['type_utilisateur']) ? 'input-error' : '' ?>" required><option value="">-- Sélectionner --</option><option value="patient" <?= (isset($form_data_forgot_pass_modal['type_utilisateur']) && $form_data_forgot_pass_modal['type_utilisateur'] === 'patient') ? 'selected' : '' ?>>Patient</option><option value="medecin" <?= (isset($form_data_forgot_pass_modal['type_utilisateur']) && $form_data_forgot_pass_modal['type_utilisateur'] === 'medecin') ? 'selected' : '' ?>>Médecin</option></select><small class="form-error-message"><?= htmlspecialchars($form_errors_forgot_pass_modal['type_utilisateur'] ?? ($form_errors_forgot_pass_modal['user_type'] ?? '')) ?></small></div><div class="form-actions"><button type="submit" class="submit-button primary-action">Envoyer le lien</button></div><p class="form-switch-prompt">Revenir à la <a href="#" class="switch-modal" data-target-modal="#modal-connexion">Connexion</a></p></form> </div> </div>
<div id="map-modal-large" class="modal" role="dialog" aria-modal="true" aria-labelledby="map-modal-large-title-text"><div class="modal-content map-modal-content"> <button class="close-modal-button" aria-label="Fermer la carte">×</button><h3 id="map-modal-large-title-text" class="form-title">Localisation Détaillée</h3><div id="map-container-modal-large" style="width: 100%; height: 500px; background-color: #f0f0f0; border-radius: var(--border-radius-md);"></div></div></div>


<main class="main-content">
    <section id="accueil" class="hero-section">
        <div class="hero-overlay">
            <div class="container">
                <div class="hero-content">
                    <h1 class="hero-title"><strong class="highlight-text"><?= $nom_application_display ?></strong><br>votre plateforme de prise de rendez-vous médicaux en ligne!</h1>
                    <p class="hero-description">
                        Nous vous offrons un accès rapide et facile aux meilleurs professionnels de santé.
                        Trouvez le médecin qu'il vous faut et prenez rendez-vous en quelques clics.
                    </p>
                </div>
                <div class="search-container hero-search-container">
                    <form id="searchSpecialtyForm" class="search-form" action="pages/docteurs.php" method="GET">
                        <div class="select-with-icon">
                            <i class="fas fa-stethoscope select-icon"></i>
                            <select id="specialtySelect" name="specialite" class="search-select form-control" aria-label="Sélectionner une spécialité">
                                <option value="" selected>Recherchez une Spécialité...</option>
                                <?php foreach ($specialites_liste_complete as $spec_recherche): ?>
                                    <?php 
                                    $description = $specialites_descriptions[$spec_recherche] ?? ''; ?>
                                    <option value="<?= htmlspecialchars($spec_recherche) ?>" data-description="<?= htmlspecialchars($description) ?>">
                                        <?= htmlspecialchars($spec_recherche) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="search-button">
                            <i class="fas fa-search icon-left" aria-hidden="true"></i>Rechercher
                        </button>
                    </form>
                    <div id="specialty-description-tooltip" class="custom-tooltip" aria-live="polite" role="tooltip"></div>
                </div>
            </div>
        </div>
    </section>

    <section id="docteurs" class="doctors-section section-padding">
        <div class="container">
            <h2 class="section-title">Nos Médecins à la Une</h2>
            <p class="section-subtitle">Découvrez une sélection de nos professionnels de santé qualifiés et prêts à vous accueillir.</p>
            <div class="doctor-list" id="apercuDoctorListContainer">
                <?php if (count($apercu_medecins) > 0): ?>
                    <?php foreach ($apercu_medecins as $med): ?>
                        <div class="doctor-card" data-medecin-id="<?= $med['id'] ?>">
                             <div class="doctor-card-image-wrapper">
                                <img src="<?= $med['photo'] ? htmlspecialchars($med['photo']) : 'assets/images/placeholder-medecin.jpg' ?>"
                                     alt="Dr. <?= htmlspecialchars($med['prenom'] . ' ' . $med['nom']) ?>"
                                     class="doctor-card-image">
                            </div>
                            <div class="doctor-card-content">
                                <h3 class="doctor-name">Dr. <?= htmlspecialchars($med['prenom'] . ' ' . $med['nom']) ?></h3>
                                <p class="doctor-specialty"><?= htmlspecialchars($med['specialite']) ?></p>

                                <?php
                                if (isset($med['latitude']) && is_numeric($med['latitude']) && isset($med['longitude']) && is_numeric($med['longitude'])): ?>
                                    <div id="map-medecin-index-<?= $med['id'] ?>" class="doctor-card-map-container"
                                         data-latitude="<?= $med['latitude'] ?>"
                                         data-longitude="<?= $med['longitude'] ?>"
                                         data-medecin-nom="Dr. <?= htmlspecialchars($med['prenom'] . ' ' . $med['nom']) ?>"
                                         title="Cliquez pour agrandir la carte">
                                         <span class="map-overlay-text">Agrandir</span>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center" style="font-size:0.85em; padding: var(--spacing-md) 0;">Localisation non disponible.</p>
                                <?php endif; ?>

                                <div class="doctor-horaires-toggle-container">
                                    <button type="button" class="btn btn-xs btn-outline-primary doctor-horaires-toggle-btn">
                                        <i class="far fa-clock icon-left"></i>Voir les Horaires
                                    </button>
                                    <div class="doctor-horaires-details" style="display: none;">
                                        <?= !empty($med['horaires']) ? nl2br(htmlspecialchars($med['horaires'])) : '<p><i>Horaires non spécifiés.</i></p>' ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($med['telephone'])): ?>
                                <p class="doctor-address" style="margin-top: var(--spacing-sm);">
                                    <i class="fas fa-phone-alt icon-left"></i>
                                    <a href="tel:<?= htmlspecialchars($med['telephone']) ?>"><?= htmlspecialchars($med['telephone']) ?></a>
                                </p>
                                <?php endif; ?>
                                 <p class="doctor-address">
                                    <i class="fas fa-map-marker-alt icon-left"></i>
                                    <?= $med['adresse'] ? htmlspecialchars(mb_strimwidth($med['adresse'], 0, 40, "...")) : 'Adresse non spécifiée' ?>
                                </p>
                                <div class="doctor-card-actions">
                                    <a href="pages/rendez-vous.php?medecin_id=<?= $med['id'] ?>&medecin_nom=<?= urlencode('Dr. ' . $med['prenom'] . ' ' . $med['nom']) ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-calendar-check icon-left"></i>Prendre RDV
                                    </a>
                                     <a href="pages/docteurs.php?focus_med_id=<?= $med['id'] ?>&specialite=<?= urlencode($med['specialite']) ?>" class="btn btn-sm btn-outline-primary">
                                         Voir Profil
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center info-message" style="grid-column: 1 / -1;">Bientôt, la liste de nos médecins partenaires s'affichera ici.</p>
                <?php endif; ?>
            </div>
            <div class="text-center section-action-link" style="margin-top: var(--spacing-xl);">
                <a href="pages/docteurs.php" class="btn primary-action btn-lg">Voir tous nos médecins</a>
            </div>
        </div>
    </section>

    <section id="apropos" class="about-section section-padding">
        <div class="container">
            <h2 class="section-title">À Propos de <?= $nom_application_display ?></h2>
            <p class="section-subtitle" style="max-width: 800px; line-height:1.8;">
                <?= $nom_application_display ?> est votre partenaire de confiance pour accéder facilement et rapidement aux services de santé.
                Notre mission est de simplifier la prise de rendez-vous médicaux et de vous connecter avec des professionnels qualifiés,
                tout en vous offrant une plateforme intuitive et sécurisée. Nous croyons en un accès équitable aux soins pour tous.
                Rejoignez notre communauté et prenez en main votre santé dès aujourd'hui !
            </p>
        </div>
     </section>

     <section id="temoignages" class="testimonials-section section-padding" style="background-color: var(--bg-highlight);">
         <div class="container">
             <h2 class="section-title">Ce que disent nos utilisateurs</h2>
             <div class="testimonial-slider" id="testimonialSlider">
                 <p id="loading-testimonials" class="info-message text-center">
                    <i class="fas fa-spinner fa-spin icon-left"></i> Chargement des témoignages...
                </p>
                <p id="no-testimonials-found" class="info-message text-center" style="display:none;">
                    <i class="fas fa-comment-slash icon-left"></i> Aucun témoignage à afficher pour le moment.
                </p>
             </div>
         </div>
     </section>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-section footer-about"><h3 class="footer-title">À propos de nous</h3><p><?= $nom_application_display ?> simplifie la prise de rendez-vous médicaux et vous connecte aux meilleurs spécialistes.</p></div>
            <div class="footer-section footer-links"><h3 class="footer-title">Navigation</h3><ul>
                <li><a href="#accueil">Accueil</a></li>
                <li><a href="#docteurs">Nos médecins</a></li>
                <li><a href="#apropos">À Propos</a></li>
                <li><a href="pages/contact.php">Contactez-nous</a></li>
                <li><a href="pages/faq.php">FAQ</a></li></ul>
            </div>
            <div class="footer-section footer-contact"><h3 class="footer-title">Nous Joindre</h3><p><i class="fas fa-envelope icon-left"></i><a href="mailto:<?= $email_contact_display ?>"><?= $email_contact_display ?></a></p><p><i class="fas fa-phone icon-left"></i><a href="tel:+212656629464">+212 6 56 62 94 64</a></p></div>
            <div class="footer-section footer-comment-form"><h3 class="footer-title">Votre Avis Compte</h3><form id="commentFormFooter" action="php/soumettre_commentaire.php" method="POST" class="user-form"><input type="hidden" name="form_origin_commentaire" value="index.php#commentFormFooter"><?= csrf_input_field() ?><div class="form-group"><label for="nom_commentaire_footer" class="sr-only">Nom</label><input type="text" id="nom_commentaire_footer" name="nom_commentaire" placeholder="Votre nom" required class="form-control"></div><div class="form-group"><label for="message_commentaire_footer" class="sr-only">Avis</label><textarea id="message_commentaire_footer" name="message_commentaire" placeholder="Votre avis..." required rows="3" class="form-control"></textarea></div><button type="submit" class="submit-button primary-action btn-sm btn-block">Envoyer Avis</button></form></div>
        </div>
        <div class="footer-social-admin"><div class="social-icons"><a href="#" aria-label="Facebook <?= $nom_application_display ?>"><i class="fab fa-facebook-f"></i></a><a href="#" aria-label="Twitter <?= $nom_application_display ?>"><i class="fab fa-twitter"></i></a><a href="#" aria-label="Instagram <?= $nom_application_display ?>"><i class="fab fa-instagram"></i></a></div><div class="admin-space-link"><a href="pages/admin-login.php">Espace Administrateur</a></div></div>
        <div class="footer-bottom"><p class="copyright-text">© <span id="footer-year"></span> <?= $nom_application_display ?>. Tous droits réservés.</p></div>
    </div>
</footer>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="assets/js/script.js"></script>
</body>
</html>