<?php
session_start(); 
require_once __DIR__ . '/../php/utils/csrf_utils.php';

$form_data_connexion_page = $_SESSION['form_data_connexion_page'] ?? []; 
$form_errors_connexion_page = $_SESSION['form_errors_connexion_page'] ?? [];
unset($_SESSION['form_data_connexion_page'], $_SESSION['form_errors_connexion_page']);

$flash_message_page = $_SESSION['flash_message_page'] ?? ($_SESSION['flash_message_login'] ?? null);
$flash_type_page = $_SESSION['flash_type_page'] ?? ($_SESSION['flash_type_login'] ?? '');
unset($_SESSION['flash_message_page'], $_SESSION['flash_type_page']);
unset($_SESSION['flash_message_login'], $_SESSION['flash_type_login']);

$inscription_succes_patient = isset($_GET['inscription']) && $_GET['inscription'] === 'succes_patient';
if ($inscription_succes_patient && !$flash_message_page) { 
    $flash_message_page = "Inscription réussie ! Vous pouvez maintenant vous connecter.";
    $flash_type_page = "success";
}
$inscription_succes_med = isset($_GET['inscription']) && $_GET['inscription'] === 'succes_med';
if ($inscription_succes_med && !$flash_message_page) { 
    $flash_message_page = "Votre demande d'inscription a été soumise. Vous serez notifié(e) après validation.";
    $flash_type_page = "info"; 
}
$reset_pass_succes = isset($_GET['reset']) && $_GET['reset'] === 'succes';
if ($reset_pass_succes && !$flash_message_page) {
    $flash_message_page = "Votre mot de passe a été réinitialisé avec succès. Veuillez vous connecter.";
    $flash_type_page = "success";
}

if (isset($_GET['email']) && empty($form_data_connexion_page['email'])) {
    $form_data_connexion_page['email'] = htmlspecialchars($_GET['email']);
}

$csrf_token_connexion_page = generate_csrf_token();
?> 
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion - SANTE TV</title>
  <meta name="description" content="Connectez-vous à votre espace patient ou médecin sur SANTE TV pour gérer vos rendez-vous et informations.">
  <link rel="stylesheet" href="../assets/css/styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="body-auth-user"> 

<header class="site-header auth-header"> 
    <div class="container">
        <div class="logo-branding">
            <a href="../index.php" title="Retour à l'accueil de SANTE TV">
                <img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img">
                <span class="site-title">SANTE TV</span>
            </a>
        </div>
        <nav class="main-navigation">
            <ul>
                <li><a href="../index.php" class="nav-link">Accueil</a></li>
                <li><a href="inscription_patient.php" class="nav-link">S'inscrire (Patient)</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content auth-page-container">
    <div class="auth-form-wrapper">
        <h1 class="form-title">Connectez-vous à votre Espace</h1>
        
        <?php if ($flash_message_page): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_page) ?> alert-dismissible">
                <?= htmlspecialchars($flash_message_page) ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($form_errors_connexion_page['_general'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($form_errors_connexion_page['_general']) ?></div>
        <?php endif; ?>

        <form id="userLoginPageForm" action="../php/connexion.php" method="post" class="user-form">
            <?= csrf_input_field() ?>
            <input type="hidden" name="form_origin_connexion" value="../pages/connexion.php">

            <div class="form-group">
                <label for="type_utilisateur_page_connexion">Je suis : <span class="text-danger">*</span></label>
                <div class="select-with-icon">
                    <i class="fas fa-user-tag select-icon"></i>
                    <select name="type_utilisateur" id="type_utilisateur_page_connexion" class="form-control <?= isset($form_errors_connexion_page['type_utilisateur']) ? 'input-error' : '' ?>" required>
                      <option value="">-- Sélectionner votre profil --</option>
                      <option value="patient" <?= (isset($form_data_connexion_page['type_utilisateur']) && $form_data_connexion_page['type_utilisateur'] === 'patient') ? 'selected' : '' ?>>Patient</option>
                      <option value="medecin" <?= (isset($form_data_connexion_page['type_utilisateur']) && $form_data_connexion_page['type_utilisateur'] === 'medecin') ? 'selected' : '' ?>>Médecin</option>
                    </select>
                </div>
                <small class="form-error-message"><?= htmlspecialchars($form_errors_connexion_page['type_utilisateur'] ?? '') ?></small>
            </div>

            <div class="form-group">
                <label for="email_page_connexion">Email : <span class="text-danger">*</span></label>
                <div class="input-with-icon">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" id="email_page_connexion" class="form-control <?= isset($form_errors_connexion_page['email']) ? 'input-error' : '' ?>" 
                           value="<?= htmlspecialchars($form_data_connexion_page['email'] ?? '') ?>" required placeholder="exemple@domaine.com">
                </div>
                <small class="form-error-message"><?= htmlspecialchars($form_errors_connexion_page['email'] ?? '') ?></small>
            </div>

            <div class="form-group">
                <label for="mot_de_passe_page_connexion">Mot de passe : <span class="text-danger">*</span></label>
                 <div class="input-with-icon">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="mot_de_passe" id="mot_de_passe_page_connexion" class="form-control <?= isset($form_errors_connexion_page['mot_de_passe']) ? 'input-error' : '' ?>" required placeholder="Votre mot de passe">
                </div>
                <small class="form-error-message"><?= htmlspecialchars($form_errors_connexion_page['mot_de_passe'] ?? '') ?></small>
            </div>
            
            <p class="text-right" style="margin-top: -0.5rem; margin-bottom: 1rem; font-size: 0.85rem;">
                <a href="mot_de_passe_oublie.php" class="link-discret">Mot de passe oublié ?</a>
            </p>

            <div class="form-actions">
                <button type="submit" class="submit-button primary-action btn-block">
                    <i class="fas fa-sign-in-alt icon-left"></i>Se connecter
                </button>
            </div>
        </form>
        
        <p class="form-switch-prompt text-center" style="margin-top:1.5rem;">
            Pas encore de compte patient ? 
            <a href="inscription_patient.php" class="link-emphasis">Inscrivez-vous ici</a>.
        </p>
         <p class="form-switch-prompt text-center" style="margin-top:0.5rem;">
            Médecin et souhaitez nous rejoindre ? 
            <a href="inscription_medecin.php" class="link-emphasis">Déposez votre demande</a>.
        </p>
        
        <p class="text-center" style="margin-top: 2rem;">
            <a href="../index.php" class="link-discret"><i class="fas fa-arrow-left icon-left"></i>Retour à l'accueil du site</a>
        </p>
    </div>
</main>

<footer class="site-footer auth-footer">
    <div class="container">
        <p class="copyright-text text-center">© <span id="footer-year"></span> SANTE TV Plus. Tous droits réservés.</p>
    </div>
</footer>

<script src="../assets/js/script.js"></script>
</body>
</html>