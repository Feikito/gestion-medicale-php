<?php
session_start();
require_once '../php/utils/csrf_utils.php'; // Pour le token CSRF

// Récupérer les données de formulaire et erreurs de la session (si retour après soumission PHP échouée)
$form_data_admin_login = $_SESSION['form_data_admin_login'] ?? [];
$form_errors_admin_login = $_SESSION['form_errors_admin_login'] ?? [];
unset($_SESSION['form_data_admin_login'], $_SESSION['form_errors_admin_login']);

// Message flash général (pour succès/erreur globale après soumission)
$flash_message_admin_login = $_SESSION['flash_message'] ?? null;
$flash_type_admin_login = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Si l'admin est déjà connecté, le rediriger vers son tableau de bord
if (isset($_SESSION['admin_id'])) {
    header('Location: ../admin/dashboard_admin.php'); // Ajuster chemin si dashboard_admin.php est ailleurs
    exit;
}

$csrf_token_admin_login = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Administrateur - SANTE TV</title>
    <meta name="robots" content="noindex, nofollow"> <!-- Empêcher l'indexation de la page de login admin -->
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body class="body-auth-admin">

<div class="auth-page-container">
    <div class="auth-form-wrapper">
        <div class="auth-logo text-center">
            <a href="index.php" title="Accueil SANTE TV">
                <img src="../assets/images/logo1.png" alt="SANTE TV Logo" style="max-width: 90px; margin-bottom: 1rem;">
            </a>
            <h2>Espace Sécurisé Administrateur</h2>
        </div>

        <?php if ($flash_message_admin_login): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_admin_login) ?> alert-dismissible" style="margin-bottom:1rem;">
                <?= htmlspecialchars($flash_message_admin_login) ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($form_errors_admin_login['_general'])): ?>
            <div class="alert alert-danger" style="margin-bottom:1rem;"><?= htmlspecialchars($form_errors_admin_login['_general']) ?></div>
        <?php endif; ?>

        <form id="adminLoginForm" action="../php/connexion_admin.php" method="post" class="user-form">
            <?= csrf_input_field() ?>
            <input type="hidden" name="form_origin_admin_login" value="../pages/admin-login.php">

            <div class="form-group">
                <label for="email-admin-login">Email Administrateur : <span class="text-danger">*</span></label>
                <input type="email" name="email" id="email-admin-login" class="form-control <?= isset($form_errors_admin_login['email']) ? 'input-error' : '' ?>" 
                       value="<?= htmlspecialchars($form_data_admin_login['email'] ?? '') ?>" required>
                <small class="form-error-message"><?= htmlspecialchars($form_errors_admin_login['email'] ?? '') ?></small>
            </div>

            <div class="form-group">
                <label for="mot_de_passe-admin-login">Mot de passe : <span class="text-danger">*</span></label>
                <input type="password" name="mot_de_passe" id="mot_de_passe-admin-login" class="form-control <?= isset($form_errors_admin_login['mot_de_passe']) ? 'input-error' : '' ?>" required>
                <small class="form-error-message"><?= htmlspecialchars($form_errors_admin_login['mot_de_passe'] ?? '') ?></small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="submit-button primary-action btn-block">
                    <i class="fas fa-sign-in-alt icon-left"></i>Se connecter
                </button>
            </div>
        </form>
        <p class="text-center" style="margin-top: 1.5rem; font-size: 0.9rem;">
            <a href="../index.php" class="link-discret"><i class="fas fa-arrow-left icon-left"></i> Retour à l'accueil du site</a>
        </p>
    </div>
</div>

<!-- Styles CSS spécifiques (si certains ne sont pas déjà dans styles.css) -->
<style>
    .text-danger { color: var(--color-danger); font-weight: normal; }
    .btn-block { width: 100%; }
    .link-discret { color: var(--text-color-secondary); text-decoration: none; }
    .link-discret:hover { color: var(--color-brand-blue); text-decoration: underline; }
    .auth-logo img { transition: transform 0.3s ease; }
    .auth-logo img:hover { transform: scale(1.05); }
    .icon-left { margin-right: 0.5em; }
</style>

<script src="../assets/js/script.js"></script>
<!-- Le JS de validation est dans script.js et ciblera les ID de ce formulaire -->
</body>
</html>