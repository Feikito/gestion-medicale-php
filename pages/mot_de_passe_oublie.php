<?php
session_start();
require_once __DIR__ . '/../php/utils/csrf_utils.php'; 
require_once __DIR__ . '/../php/utils/app_settings.php'; 

if (isset($_SESSION['utilisateur_id']) && isset($_SESSION['type'])) {
    if ($_SESSION['type'] === 'patient') {
        header('Location: ../php/dashboard_patient.php'); exit;
    } elseif ($_SESSION['type'] === 'medecin') {
        header('Location: ../php/espace_medecin.php'); exit;
    }
} elseif (isset($_SESSION['admin_id'])) {
    header('Location: ../admin/dashboard_admin.php'); exit;
}

$form_data_forgot_pass_page = $_SESSION['form_data_forgot_pass'] ?? [];
$form_errors_forgot_pass_page = $_SESSION['form_errors_forgot_pass'] ?? [];
unset($_SESSION['form_data_forgot_pass'], $_SESSION['form_errors_forgot_pass']);

$flash_message_forgot_pass_page = $_SESSION['flash_message'] ?? null;
$flash_type_forgot_pass_page = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$csrf_token_forgot_pass_page = generate_csrf_token();
$nom_application_display_forgot_pass = defined('NOM_APPLICATION') ? htmlspecialchars(NOM_APPLICATION) : 'SANTE TV';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de Passe Oublié - <?= $nom_application_display_forgot_pass ?></title>
    <meta name="description" content="Réinitialisez votre mot de passe <?= $nom_application_display_forgot_pass ?> si vous l'avez oublié. Entrez votre email et type de compte pour recevoir un lien de réinitialisation.">
    <meta name="robots" content="noindex, nofollow"> 
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body class="body-auth-user">

<header class="site-header auth-header">
    <div class="container">
        <div class="logo-branding">
            <a href="../index.php" title="Retour à l'accueil de <?= $nom_application_display_forgot_pass ?>">
                <img src="../assets/images/logo1.png" alt="<?= $nom_application_display_forgot_pass ?> Logo" id="logo-img" style="height: 45px;">
                <span class="site-title" style="color: var(--color-primary-dark);"><?= $nom_application_display_forgot_pass ?></span>
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
    <div class="auth-form-wrapper" style="max-width: 500px;">
        <h1 class="form-title" style="font-size: 1.7rem;">Réinitialiser votre Mot de Passe</h1>
        <p class="text-center text-muted" style="margin-bottom: 1.5rem;">
            Entrez l'adresse e-mail associée à votre compte et sélectionnez votre type de profil. Si un compte correspondant est trouvé, nous vous enverrons un lien pour réinitialiser votre mot de passe.
        </p>

        <?php if ($flash_message_forgot_pass_page): ?>
            <div id="feedbackForgotPasswordPage" class="alert alert-<?= htmlspecialchars($flash_type_forgot_pass_page) ?> alert-dismissible" style="margin-bottom:1rem; word-break: break-word;">
                <?= nl2br(htmlspecialchars($flash_message_forgot_pass_page)) ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button>
            </div>
        <?php endif; ?>

         <?php if (isset($form_errors_forgot_pass_page['_general'])): ?>
            <div class="alert alert-danger" style="margin-bottom:1rem;"><?= htmlspecialchars($form_errors_forgot_pass_page['_general']) ?></div>
        <?php endif; ?>

        <form id="forgotPasswordPageForm" action="../php/request_password_reset.php" method="POST" class="user-form">
            <?= csrf_input_field() ?>
             <input type="hidden" name="form_origin_forgot_password" value="../pages/mot_de_passe_oublie.php">

            <div class="form-group">
                <label for="email-forgot-page">Adresse Email : <span class="text-danger">*</span></label>
                <input type="email" id="email-forgot-page" name="email" class="form-control <?= isset($form_errors_forgot_pass_page['email']) ? 'input-error' : '' ?>" 
                       value="<?= htmlspecialchars($form_data_forgot_pass_page['email'] ?? '') ?>" required>
                <small class="form-error-message"><?= htmlspecialchars($form_errors_forgot_pass_page['email'] ?? '') ?></small>
            </div>

            <div class="form-group">
                 <label for="user_type_forgot_page">Je suis un : <span class="text-danger">*</span></label>
                 <select name="type_utilisateur" id="user_type_forgot_page" class="form-control <?= isset($form_errors_forgot_pass_page['type_utilisateur']) ? 'input-error' : '' ?>" required>
                    <option value="">-- Sélectionner le type de compte --</option>
                    <option value="patient" <?= (isset($form_data_forgot_pass_page['type_utilisateur']) && $form_data_forgot_pass_page['type_utilisateur'] === 'patient') ? 'selected' : '' ?>>Patient</option>
                    <option value="medecin" <?= (isset($form_data_forgot_pass_page['type_utilisateur']) && $form_data_forgot_pass_page['type_utilisateur'] === 'medecin') ? 'selected' : '' ?>>Médecin</option>
                 </select>
                 <small class="form-error-message"><?= htmlspecialchars($form_errors_forgot_pass_page['type_utilisateur'] ?? ($form_errors_forgot_pass_page['user_type'] ?? '')) ?></small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn primary-action btn-block">
                    <i class="fas fa-envelope icon-left"></i>Envoyer le lien de réinitialisation
                </button>
            </div>
        </form>
        <p class="text-center" style="margin-top: 1.5rem;">
            <a href="connexion.php" class="link-discret"><i class="fas fa-arrow-left icon-left"></i> Retour à la page de connexion</a>
        </p>
    </div>
</main>

<footer class="site-footer" style="margin-top: 3rem;">
    <div class="container">
        <p class="copyright-text text-center">© <span id="footer-year"><?= date('Y') ?></span> <?= $nom_application_display_forgot_pass ?>. Tous droits réservés.</p>
    </div>
</footer>

<script src="../assets/js/script.js"></script>
</body>
</html>