<?php
session_start();
require '../php/db.php'; 
require_once '../php/utils/csrf_utils.php';

if (!isset($_SESSION['admin_id'])) {
    $_SESSION['flash_message_login'] = "Accès refusé. Veuillez vous connecter en tant qu'administrateur.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/admin-login.php'); 
    exit;
}

$admin_nom_display_admin_email = $_SESSION['admin_nom'] ?? 'Administrateur';
$csrf_token_admin_email_masse = generate_csrf_token();
$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$form_data = $_SESSION['form_data_email_masse'] ?? [];
$form_errors = $_SESSION['form_errors_email_masse'] ?? [];
unset($_SESSION['form_data_email_masse'], $_SESSION['form_errors_email_masse']);

$nb_medecins_attente = 0; $nb_commentaires_attente = 0;
try {
    if ($pdo->query("SHOW TABLES LIKE 'medecins'")->rowCount() > 0) {
        $nb_medecins_attente = $pdo->query("SELECT COUNT(*) FROM medecins WHERE valide = 0")->fetchColumn();
    }
    if ($pdo->query("SHOW TABLES LIKE 'commentaires'")->rowCount() > 0) {
        $nb_commentaires_attente = $pdo->query("SELECT COUNT(*) FROM commentaires WHERE statut = 'en attente'")->fetchColumn();
    }
} catch (PDOException $e) { error_log("Erreur récupération badges pour admin_send_email_masse: " . $e->getMessage()); }

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envoyer Email en Masse - Administration SANTE TV</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tiny.cloud/1/4amskh9mj3cii8jraizm25oi6k5kvplfgmzr48dm2b52fgj7/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
      tinymce.init({
        selector: 'textarea#message_email_masse',
        plugins: 'lists link image charmap preview anchor pagebreak searchreplace wordcount visualblocks code fullscreen insertdatetime media table help',
        toolbar: 'undo redo | styles | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media | forecolor backcolor emoticons | preview fullscreen | help',
        menubar: 'file edit view insert format tools table help',
        height: 400,
        language: 'fr_FR',
        language_url: '../assets/js/tinymce_langs/fr_FR.js' 
      });
    </script>
</head>
<body class="admin-gestion-page body-admin-send-email-masse"> 

<header class="site-header admin-header">
    <div class="container">
         <div class="logo-branding">
            <a href="dashboard_admin.php" title="Tableau de Bord Admin SANTE TV"> 
                <img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img">
                <span class="site-title">Admin SANTE TV</span>
            </a>
        </div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav">
            <span></span><span></span><span></span>
        </button>
         <nav class="main-navigation admin-navigation" id="main-nav">
            <ul>
                <li><a href="dashboard_admin.php" class="nav-link">Dashboard</a></li>
                <li><a href="gestion_medecins.php" class="nav-link">Médecins <?php if($nb_medecins_attente > 0): ?><span class="badge-notification"><?= $nb_medecins_attente ?></span><?php endif; ?></a></li>
                <li><a href="gestion_patients.php" class="nav-link">Patients</a></li>
                <li><a href="envoyer_emails_masse.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'envoyer_emails_masse.php' || basename($_SERVER['PHP_SELF']) == 'envoyer_email_specifique.php') ? 'active' : ''; ?>"> <i class="fas fa-mail-bulk icon-left"></i>Email en Masse</a></li>
                <li><a href="envoyer_email_specifique.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'envoyer_email_specifique.php') ? 'active' : ''; ?>">Email Spécifique</a></li>
                <li><a href="deconnexion_admin.php" class="nav-link btn btn-sm btn-danger">Déconnexion</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content admin-page-container section-padding"> 
    <div class="container">
        <div class="page-header">
            <h1 class="page-main-title"><i class="fas fa-mail-bulk page-icon" style="color: var(--color-accent-gold);"></i> Envoyer un Email en Masse</h1>
        </div>

        <?php if ($flash_message): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type) ?> alert-dismissible">
                <?= $flash_message ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button> 
            </div>
        <?php endif; ?>

        <div class="card" style="padding: var(--spacing-xl);">
            <form action="traitement_envoi_emails_masse.php" method="POST" class="user-form">
                <?= csrf_input_field() ?>
                
                <div class="form-group">
                    <label for="destinataires_groupe">Groupe de destinataires : <span class="text-danger">*</span></label>
                    <select name="destinataires_groupe" id="destinataires_groupe" class="form-control <?= isset($form_errors['destinataires_groupe']) ? 'input-error' : '' ?>" required>
                        <option value="">-- Sélectionner un groupe --</option>
                        <option value="tous_patients" <?= ($form_data['destinataires_groupe'] ?? '') === 'tous_patients' ? 'selected' : '' ?>>Tous les Patients</option>
                        <option value="tous_medecins" <?= ($form_data['destinataires_groupe'] ?? '') === 'tous_medecins' ? 'selected' : '' ?>>Tous les Médecins</option>
                        <option value="medecins_valides" <?= ($form_data['destinataires_groupe'] ?? '') === 'medecins_valides' ? 'selected' : '' ?>>Médecins Validés</option>
                        <option value="medecins_attente" <?= ($form_data['destinataires_groupe'] ?? '') === 'medecins_attente' ? 'selected' : '' ?>>Médecins en Attente de Validation</option>
                        <option value="tous_utilisateurs" <?= ($form_data['destinataires_groupe'] ?? '') === 'tous_utilisateurs' ? 'selected' : '' ?>>Tous les Utilisateurs (Patients + Médecins)</option>
                    </select>
                    <small class="form-error-message"><?= htmlspecialchars($form_errors['destinataires_groupe'] ?? '') ?></small>
                </div>

                <div class="form-group">
                    <label for="sujet_masse">Sujet : <span class="text-danger">*</span></label>
                    <input type="text" name="sujet" id="sujet_masse" class="form-control <?= isset($form_errors['sujet']) ? 'input-error' : '' ?>" 
                           value="<?= htmlspecialchars($form_data['sujet'] ?? '') ?>" required
                           placeholder="Sujet de votre communication">
                    <small class="form-error-message"><?= htmlspecialchars($form_errors['sujet'] ?? '') ?></small>
                </div>
                <div class="form-group">
                    <label for="message_email_masse">Message : <span class="text-danger">*</span></label>
                    <p class="form-note">Vous pouvez utiliser `%NOM_UTILISATEUR%` qui sera remplacé par le nom complet du destinataire (ex: "Bonjour %NOM_UTILISATEUR%,").</p>
                    <textarea name="message" id="message_email_masse" rows="15" class="form-control <?= isset($form_errors['message']) ? 'input-error' : '' ?>"><?= htmlspecialchars($form_data['message'] ?? '') ?></textarea>
                    <small class="form-error-message"><?= htmlspecialchars($form_errors['message'] ?? '') ?></small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn primary-action btn-lg" onclick="return confirm('Êtes-vous sûr de vouloir envoyer cet email à ce groupe d\'utilisateurs ? Cette action est irréversible.');">
                        <i class="fas fa-paper-plane icon-left"></i>Envoyer l'Email en Masse
                    </button>
                </div>
            </form>
            <?php unset($_SESSION['form_data_email_masse'], $_SESSION['form_errors_email_masse']); ?>
        </div>
    </div>
</main>

<footer class="site-footer admin-footer">
   <div class="container">
        <p class="copyright-text text-center">© <span id="footer-year"></span> SANTE TV - Espace Administration.</p>
    </div>
</footer>

<script src="../assets/js/script.js"></script> 
</body>
</html>