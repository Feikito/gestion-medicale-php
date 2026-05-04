<?php
session_start();
require_once __DIR__ . '/../php/utils/csrf_utils.php'; // Ajusté pour être relatif à pages/

$form_data_med_page = $_SESSION['form_data_medecin_page'] ?? [];
$form_errors_med_page = $_SESSION['form_errors_medecin_page'] ?? [];
unset($_SESSION['form_data_medecin_page'], $_SESSION['form_errors_medecin_page']);

$flash_message_page = $_SESSION['flash_message_page'] ?? null;
$flash_type_page = $_SESSION['flash_type_page'] ?? '';
unset($_SESSION['flash_message_page'], $_SESSION['flash_type_page']);

// Utilisation de la même liste de spécialités que index.php, incluant "Autre"
$specialites_liste_complete = [
    "Cardiologie", "Chirurgie cardiaque", "Chirurgie générale", "Chirurgien orthopédique",
    "Dermatologie", "Endocrinologie", "Gastro-entérologue", "Gériatre", "Interniste",
    "Médecine générale", "Néphrologue", "Neurochirurgien", "Oncologue", "Ophtalmologie",
    "ORL", "Pédiatrie", "Pneumologue", "Psychiatrie", "Radiologue", "Rhumatologue",
    "Autre" // Ajout de "Autre" ici
];
// sort($specialites_liste_complete); // Si vous voulez la trier alphabétiquement après ajout

$csrf_token_insc_med_page = generate_csrf_token();
$nom_application_display_insc_med = defined('NOM_APPLICATION') ? htmlspecialchars(NOM_APPLICATION) : 'SANTE TV';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Médecin - Rejoignez <?= $nom_application_display_insc_med ?></title>
    <meta name="description" content="Professionnels de santé, inscrivez-vous sur <?= $nom_application_display_insc_med ?> pour proposer vos services, gérer vos rendez-vous et atteindre plus de patients.">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body class="body-auth-user">

<header class="site-header auth-header">
    <div class="container">
        <div class="logo-branding">
            <a href="../index.php" title="Retour à l'accueil de <?= $nom_application_display_insc_med ?>">
                <img src="../assets/images/logo1.png" alt="<?= $nom_application_display_insc_med ?> Logo" id="logo-img" style="height: 45px;">
                <span class="site-title" style="color: var(--color-primary-dark);"><?= $nom_application_display_insc_med ?></span>
            </a>
        </div>
        <nav class="main-navigation">
            <ul>
                <li><a href="../index.php" class="nav-link" style="color: var(--color-primary);">Accueil</a></li>
                <li><a href="connexion.php" class="nav-link" style="color: var(--color-primary);">Se Connecter</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content auth-page-container">
    <div class="auth-form-wrapper" style="max-width: 700px;">
        <h1 class="form-title" style="font-size: 1.8rem;">Devenez Partenaire <?= $nom_application_display_insc_med ?></h1>
        <p class="text-center text-muted" style="margin-bottom: 1.5rem;">Rejoignez notre réseau de professionnels de santé. Remplissez le formulaire ci-dessous pour soumettre votre demande d'inscription.</p>

        <?php if ($flash_message_page): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_page) ?> alert-dismissible" style="margin-bottom:1rem;">
                <?= htmlspecialchars($flash_message_page) ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button>
            </div>
        <?php endif; ?>

        <form id="inscriptionMedecinPageForm" action="../php/inscription_medecin.php" method="post" enctype="multipart/form-data" class="user-form">
            <?= csrf_input_field() ?>
            <input type="hidden" name="form_origin_medecin" value="../pages/inscription_medecin.php">

            <?php if (isset($form_errors_med_page['_general'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($form_errors_med_page['_general']) ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label for="nom-med-page">Nom : <span class="text-danger">*</span></label>
                <input type="text" id="nom-med-page" name="nom" class="form-control <?= isset($form_errors_med_page['nom']) ? 'input-error' : '' ?>"
                       value="<?= htmlspecialchars($form_data_med_page['nom'] ?? '') ?>" required>
                <small class="form-error-message"><?= htmlspecialchars($form_errors_med_page['nom'] ?? '') ?></small>
            </div>
            <div class="form-group">
                <label for="prenom-med-page">Prénom : <span class="text-danger">*</span></label>
                <input type="text" id="prenom-med-page" name="prenom" class="form-control <?= isset($form_errors_med_page['prenom']) ? 'input-error' : '' ?>"
                       value="<?= htmlspecialchars($form_data_med_page['prenom'] ?? '') ?>" required>
                <small class="form-error-message"><?= htmlspecialchars($form_errors_med_page['prenom'] ?? '') ?></small>
            </div>
            <div class="form-group">
                <label for="specialite-med-page">Spécialité : <span class="text-danger">*</span></label>
                <select id="specialite-med-page" name="specialite" class="form-control <?= isset($form_errors_med_page['specialite']) ? 'input-error' : '' ?>" required>
                    <option value="">Sélectionner une spécialité</option>
                    <?php foreach($specialites_liste_complete as $spec): ?>
                        <option value="<?= htmlspecialchars($spec) ?>" <?= (isset($form_data_med_page['specialite']) && $form_data_med_page['specialite'] === $spec) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($spec) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-error-message"><?= htmlspecialchars($form_errors_med_page['specialite'] ?? '') ?></small>
            </div>
            <div class="form-group">
                <label for="email-med-page">Email Professionnel : <span class="text-danger">*</span></label>
                <input type="email" id="email-med-page" name="email" class="form-control <?= isset($form_errors_med_page['email']) ? 'input-error' : '' ?>"
                       value="<?= htmlspecialchars($form_data_med_page['email'] ?? '') ?>" required>
                <small class="form-error-message"><?= htmlspecialchars($form_errors_med_page['email'] ?? '') ?></small>
            </div>
            <div class="form-group">
                <label for="telephone-med-page">Téléphone Professionnel : <span class="text-danger">*</span></label>
                <input type="tel" id="telephone-med-page" name="telephone" class="form-control <?= isset($form_errors_med_page['telephone']) ? 'input-error' : '' ?>"
                       value="<?= htmlspecialchars($form_data_med_page['telephone'] ?? '') ?>" required pattern="[0-9\s\-\+()]{10,20}">
                <small class="form-error-message"><?= htmlspecialchars($form_errors_med_page['telephone'] ?? '') ?></small>
            </div>
            <div class="form-group">
                <label for="adresse-med-page">Adresse du Cabinet : <span class="text-danger">*</span></label>
                <input type="text" id="adresse-med-page" name="adresse" class="form-control <?= isset($form_errors_med_page['adresse']) ? 'input-error' : '' ?>"
                       value="<?= htmlspecialchars($form_data_med_page['adresse'] ?? '') ?>" required>
                <small class="form-error-message"><?= htmlspecialchars($form_errors_med_page['adresse'] ?? '') ?></small>
            </div>
            <div class="form-group">
                <label for="mot_de_passe-med-page">Mot de passe : <span class="text-danger">*</span></label>
                <input type="password" id="mot_de_passe-med-page" name="mot_de_passe" class="form-control <?= isset($form_errors_med_page['mot_de_passe']) ? 'input-error' : '' ?>" required minlength="8">
                <small class="form-note">Minimum 8 caractères.</small>
                <small class="form-error-message"><?= htmlspecialchars($form_errors_med_page['mot_de_passe'] ?? '') ?></small>
            </div>
            <div class="form-group">
                <label for="confirmer_mot_de_passe-med-page">Confirmer le mot de passe : <span class="text-danger">*</span></label>
                <input type="password" id="confirmer_mot_de_passe-med-page" name="confirmer_mot_de_passe" class="form-control <?= isset($form_errors_med_page['confirmer_mot_de_passe']) ? 'input-error' : '' ?>" required>
                <small class="form-field-feedback password-feedback" id="feedback-inscription-med-page"></small>
                <?php if (isset($form_errors_med_page['confirmer_mot_de_passe'])): ?>
                    <small class="form-error-message error-message-display" style="display:block;"><?= htmlspecialchars($form_errors_med_page['confirmer_mot_de_passe']) ?></small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="documents-med-page">Documents Justificatifs (CV, Diplômes, etc.) : <span class="text-danger">*</span></label>
                <input type="file" id="documents-med-page" name="documents" class="form-control-file <?= isset($form_errors_med_page['documents']) ? 'input-error' : '' ?>" accept=".pdf,.jpg,.jpeg,.png" required>
                <small class="form-note">Formats acceptés: PDF, JPG, PNG. Taille maximale: 5MB.</small>
                <small class="form-error-message"><?= htmlspecialchars($form_errors_med_page['documents'] ?? '') ?></small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn primary-action btn-block">Soumettre ma Demande d'Inscription</button>
            </div>
            <p class="form-switch-prompt text-center" style="margin-top: 1.5rem;">
                Déjà inscrit et en attente de validation ? <a href="connexion.php" class="link-emphasis">Connectez-vous ici</a> pour vérifier le statut.
            </p>
        </form>
    </div>
</main>

<footer class="site-footer" style="margin-top: 3rem;">
    <div class="container">
        <p class="copyright-text text-center">© <span id="footer-year"><?= date('Y') ?></span> <?= $nom_application_display_insc_med ?>. Tous droits réservés.</p>
    </div>
</footer>

<script src="../assets/js/script.js"></script>
</body>
</html>