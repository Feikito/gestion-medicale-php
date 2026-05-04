<?php
session_start();
// Ce fichier (pages/reset_password_form.php) est dans 'pages/', donc on remonte d'un niveau pour aller à 'php/'
require_once __DIR__ . '/../php/utils/csrf_utils.php'; 
require_once __DIR__ . '/../php/db.php'; 

// 1. Récupérer et valider les paramètres GET (token, email, type)
$token_from_url = trim($_GET['token'] ?? '');
$email_from_url = trim($_GET['email'] ?? '');
$user_type_from_url = trim($_GET['type'] ?? '');

$is_valid_link = false;
$error_message_link_validation = ''; 

if (empty($token_from_url) || empty($email_from_url) || empty($user_type_from_url) || 
    !in_array($user_type_from_url, ['patient', 'medecin', 'admin'])) { 
    $error_message_link_validation = "Lien de réinitialisation invalide ou incomplet. Veuillez refaire une demande.";
} else {
    // 2. Vérifier si le token est valide dans la base de données et non expiré
    try {
        $stmt_check_token = $pdo->prepare("
            SELECT id 
            FROM password_resets 
            WHERE token = :token 
              AND LOWER(email) = LOWER(:email) 
              AND user_type = :user_type 
              AND expires_at > NOW() 
              AND used = 0 
        "); 
        $stmt_check_token->execute([
            ':token' => $token_from_url,
            ':email' => $email_from_url,
            ':user_type' => $user_type_from_url
        ]);
        if ($stmt_check_token->fetch()) {
            $is_valid_link = true; 
        } else {
            $stmt_cleanup_expired = $pdo->prepare("DELETE FROM password_resets WHERE LOWER(email) = LOWER(?) AND user_type = ? AND expires_at <= NOW()");
            $stmt_cleanup_expired->execute([$email_from_url, $user_type_from_url]);
            $error_message_link_validation = "Ce lien de réinitialisation est invalide, a expiré ou a déjà été utilisé. Veuillez refaire une demande si nécessaire.";
        }
    } catch (PDOException $e) {
        error_log("Erreur PDO vérification token reset (reset_password_form.php): " . $e->getMessage());
        $error_message_link_validation = "Une erreur technique est survenue lors de la vérification de votre lien. Veuillez réessayer plus tard.";
    }
}

// Récupérer les erreurs de la session (si retour après soumission PHP du formulaire de cette page)
$form_errors_reset_pass_page = $_SESSION['form_errors_reset_pass'] ?? []; 
unset($_SESSION['form_errors_reset_pass']);

// Message flash général (le script handle_password_reset.php utilisera cette clé)
$flash_message_reset_form_page = $_SESSION['flash_message'] ?? null;
$flash_type_reset_form_page = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$csrf_token_reset_form = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialiser Votre Mot de Passe - SANTE TV</title>
    <meta name="robots" content="noindex, nofollow">
    <!-- Chemins relatifs à partir de pages/ -->
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body class="body-auth-user">

<header class="site-header auth-header">
    <div class="container">
        <div class="logo-branding">
            <!-- Lien vers index.php à la racine -->
            <a href="../index.php" title="Retour à l'accueil de SANTE TV">
                <img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img" style="height: 45px;"> <!-- STYLE: Hauteur inline -->
                <span class="site-title" style="color: var(--color-primary-dark);">SANTE TV</span> <!-- STYLE: Couleur inline -->
            </a>
        </div>
    </div>
</header>

<main class="main-content auth-page-container">
    <!-- STYLE: max-width inline -->
    <div class="auth-form-wrapper" style="max-width: 500px;">
        <h1 class="form-title" style="font-size: 1.7rem;">Définir un Nouveau Mot de Passe</h1> <!-- STYLE: font-size inline -->

        <?php if ($flash_message_reset_form_page): ?>
            <!-- STYLE: margin-bottom inline -->
            <div id="feedbackResetPasswordPage" class="alert alert-<?= htmlspecialchars($flash_type_reset_form_page) ?> alert-dismissible" style="margin-bottom:1rem;">
                <?= htmlspecialchars($flash_message_reset_form_page) ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button>
            </div>
        <?php endif; ?>

        <?php if (!$is_valid_link): ?>
            <div class="alert alert-danger">
                <p><?= htmlspecialchars($error_message_link_validation) ?></p>
                <!-- STYLE: margin-top inline -->
                <p style="margin-top:1rem;"><a href="mot_de_passe_oublie.php" class="btn btn-sm primary-action">Faire une nouvelle demande</a></p>
            </div>
        <?php else: ?>
            <!-- STYLE: margin-bottom inline -->
            <p class="text-center text-muted" style="margin-bottom: 1.5rem;">
                Veuillez entrer et confirmer votre nouveau mot de passe pour le compte associé à 
                <strong><?= htmlspecialchars($email_from_url) ?></strong> (Profil: <?= htmlspecialchars(ucfirst($user_type_from_url)) ?>).
            </p>
            
            <?php if (isset($form_errors_reset_pass_page['_general'])): ?>
                <!-- STYLE: margin-bottom inline -->
                <div class="alert alert-danger" style="margin-bottom:1rem;"><?= htmlspecialchars($form_errors_reset_pass_page['_general']) ?></div>
            <?php endif; ?>

            <!-- Action vers php/handle_password_reset.php (correct) -->
            <form id="resetPasswordFormPage" action="../php/handle_password_reset.php" method="POST" class="user-form">
                <?= csrf_input_field() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token_from_url) ?>">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email_from_url) ?>">
                <input type="hidden" name="user_type" value="<?= htmlspecialchars($user_type_from_url) ?>">
                <input type="hidden" name="form_origin_reset_pass" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>"> 

                <div class="form-group">
                    <label for="new_password_reset_page">Nouveau mot de passe : <span class="text-danger">*</span></label>
                    <input type="password" id="new_password_reset_page" name="new_password" 
                           class="form-control <?= isset($form_errors_reset_pass_page['new_password']) ? 'input-error' : '' ?>" 
                           required minlength="8">
                    <small class="form-note">Minimum 8 caractères.</small>
                    <small class="form-error-message"><?= htmlspecialchars($form_errors_reset_pass_page['new_password'] ?? '') ?></small>
                </div>

                <div class="form-group">
                    <label for="confirm_new_password_reset_page">Confirmer le nouveau mot de passe : <span class="text-danger">*</span></label>
                    <input type="password" id="confirm_new_password_reset_page" name="confirm_new_password" 
                           class="form-control <?= isset($form_errors_reset_pass_page['confirm_new_password']) ? 'input-error' : '' ?>" 
                           required>
                    <small class="form-field-feedback password-feedback" id="feedback-reset-password-page"></small> 
                    <?php if (isset($form_errors_reset_pass_page['confirm_new_password'])): ?>
                        <!-- STYLE: display inline -->
                        <small class="form-error-message error-message-display" style="display:block;"><?= htmlspecialchars($form_errors_reset_pass_page['confirm_new_password']) ?></small>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn primary-action btn-block">
                        <i class="fas fa-key icon-left"></i>Mettre à jour le mot de passe
                    </button>
                </div>
            </form>
        <?php endif; ?>
        
        <?php if ($is_valid_link): ?>
        <!-- STYLE: margin-top inline -->
        <p class="text-center" style="margin-top: 1.5rem;">
            <a href="connexion.php" class="link-discret"><i class="fas fa-arrow-left icon-left"></i> Retour à la page de connexion</a>
        </p>
        <?php endif; ?>
    </div>
</main>

<!-- STYLE: margin-top inline -->
<footer class="site-footer" style="margin-top: 3rem;">
    <div class="container">
        <p class="copyright-text text-center">© <span id="footer-year"></span> SANTE TV Plus. Tous droits réservés.</p>
    </div>
</footer>

<script src="../assets/js/script.js"></script>
</body>
</html>