<?php
session_start();
// Ce fichier (pages/contact.php) est dans 'pages/'
require_once __DIR__ . '/../php/utils/csrf_utils.php'; 

// Récupérer les données de formulaire et erreurs de la session
$form_data_contact_page = $_SESSION['form_data_contact'] ?? [];
$form_errors_contact_page = $_SESSION['form_errors_contact'] ?? [];
unset($_SESSION['form_data_contact'], $_SESSION['form_errors_contact']);

// Message flash général (le script contact_form_handler.php devrait utiliser ces clés)
$flash_message_contact_page = $_SESSION['flash_message'] ?? null;
$flash_type_contact_page = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Si l'utilisateur est connecté, on peut pré-remplir son nom et email
$default_nom = '';
$default_email = '';
if (isset($_SESSION['utilisateur_id']) && isset($_SESSION['type'])) {
    // Utiliser le nom complet stocké en session après une connexion réussie
    if (isset($_SESSION['nom'])) { 
        $default_nom = htmlspecialchars($_SESSION['nom']);
    }
    // Récupérer l'email de l'utilisateur depuis la BDD car il n'est pas stocké en session de manière standard
    // Ou, si vous le stockez en session (par ex. $_SESSION['email_utilisateur']), utilisez-le.
    // Pour cet exemple, je vais supposer que nous devons le récupérer si non présent dans form_data
    if (empty($form_data_contact_page['email'])) {
        try {
            require_once __DIR__ . '/../php/db.php'; // Inclure db.php seulement si nécessaire
            $table_user_contact = '';
            if ($_SESSION['type'] === 'patient') $table_user_contact = 'patients';
            elseif ($_SESSION['type'] === 'medecin') $table_user_contact = 'medecins';
            // elseif ($_SESSION['type'] === 'admin') $table_user_contact = 'admins'; // Si admin peut utiliser ce formulaire

            if ($table_user_contact) {
                $stmt_email_user = $pdo->prepare("SELECT email FROM $table_user_contact WHERE id = ?");
                $stmt_email_user->execute([$_SESSION['utilisateur_id']]);
                $user_email_data = $stmt_email_user->fetchColumn();
                if ($user_email_data) {
                    $default_email = htmlspecialchars($user_email_data);
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur récupération email pour contact form: " . $e->getMessage());
        }
    }
}
$nom_initial_contact = $form_data_contact_page['nom'] ?? $default_nom;
$email_initial_contact = $form_data_contact_page['email'] ?? $default_email;

$csrf_token_contact_page = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contactez-Nous - SANTE TV</title>
    <meta name="description" content="Contactez l'équipe de SANTE TV pour toute question, suggestion ou demande d'assistance concernant notre plateforme de prise de rendez-vous médicaux.">
    <!-- Chemins relatifs à partir de pages/ -->
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- STYLE: Déplacer ces styles vers styles.css sous des classes spécifiques -->
    <style>
        .contact-content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr; 
            gap: 2rem;
            align-items: flex-start; 
        }
        .contact-info-container .card { margin-bottom: 1.5rem; }
        .contact-info-container .card p { margin-bottom: 0.75rem; }
        .contact-info-container .card p i { width: 20px; text-align: center; }

        @media (max-width: 992px) {
            .contact-content-grid {
                grid-template-columns: 1fr; 
            }
            .contact-info-container { order: -1; margin-bottom: 2rem; } 
        }
    </style>
</head>
<body>

<header class="site-header"> 
    <div class="container">
        <div class="logo-branding">
            <!-- Lien vers index.php à la racine -->
            <a href="../index.php" title="Accueil SANTE TV"><img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img"><span class="site-title">SANTE TV</span></a>
        </div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav">
            <span></span><span></span><span></span>
        </button>
        <nav class="main-navigation" id="main-nav">
            <ul>
                <!-- Liens vers index.php à la racine -->
                <li><a href="../index.php#accueil" class="nav-link">ACCUEIL</a></li>
                <li><a href="docteurs.php" class="nav-link">NOS MEDECINS</a></li>
                <!-- SUGGESTION: Pour "Rejoignez Nous" et "Se Connecter", envisager de lier vers les pages dédiées -->
                <li><a href="../index.php#modal-form" class="nav-link js-open-modal-index-from-other-page">REJOIGNEZ NOUS</a></li>
                <li><a href="../index.php#apropos" class="nav-link">A PROPOS</a></li>
                <li><a href="contact.php" class="nav-link active">CONTACT</a></li>
                <?php if (isset($_SESSION['utilisateur_id'])): ?>
                    <?php if ($_SESSION['type'] === 'patient'): ?>
                        <li><a href="../php/dashboard_patient.php" class="nav-link btn-header-connect">MON ESPACE</a></li>
                    <?php elseif ($_SESSION['type'] === 'medecin'): ?>
                        <li><a href="../php/espace_medecin.php" class="nav-link btn-header-connect">MON ESPACE</a></li>
                     <?php elseif ($_SESSION['type'] === 'admin'): ?>
                        <li><a href="../admin/dashboard_admin.php" class="nav-link btn-header-connect">ADMIN</a></li>
                    <?php endif; ?>
                <?php else: ?>
                    <li><a href="../index.php#modal-connexion" class="nav-link btn-header-connect js-open-modal-index-from-other-page">SE CONNECTER</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content">
    <section class="section-padding contact-page-section">
        <div class="container">
            <div class="page-header text-center">
                <h1 class="page-main-title">Contactez-Nous</h1>
                <!-- STYLE: max-width inline -->
                <p class="section-subtitle" style="max-width: 700px;">
                    Une question ? Une suggestion ? Ou besoin d'assistance ? Notre équipe est là pour vous aider. Remplissez le formulaire ci-dessous ou utilisez nos coordonnées directes.
                </p>
            </div>

            <!-- STYLE: display et margin-bottom inline -->
            <div id="contact-form-feedback" 
                 class="alert <?= !empty($flash_message_contact_page) ? 'alert-' . htmlspecialchars($flash_type_contact_page) : '' ?>" 
                 style="<?= !empty($flash_message_contact_page) ? 'display:block; margin-bottom:1.5rem;' : 'display:none;' ?>">
                <?= htmlspecialchars($flash_message_contact_page ?? '') ?>
                <?php if(!empty($flash_message_contact_page)): ?>
                    <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button>
                <?php endif; ?>
            </div>

            <div class="contact-content-grid">
                <div class="contact-form-container card"> 
                    <!-- STYLE: font-size inline -->
                    <h2 class="card-title" style="font-size:1.5rem;"><i class="fas fa-paper-plane icon-left"></i>Envoyez-nous un message direct</h2>
                    <!-- Action vers php/contact_form_handler.php (correct) -->
                    <form id="contactFormPage" action="../php/contact_form_handler.php" method="POST" class="user-form">
                        <?= csrf_input_field() ?>
                        <!-- Origine = cette page (pages/contact.php) -->
                        <input type="hidden" name="form_origin_contact" value="../pages/contact.php"> 
                        
                        <?php if (isset($form_errors_contact_page['_general'])): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($form_errors_contact_page['_general']) ?></div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="contact-nom-page">Votre Nom : <span class="text-danger">*</span></label>
                            <input type="text" id="contact-nom-page" name="nom" class="form-control <?= isset($form_errors_contact_page['nom']) ? 'input-error' : '' ?>" 
                                   value="<?= htmlspecialchars($nom_initial_contact) ?>" required>
                            <small class="form-error-message" id="error-contact-nom-page"><?= htmlspecialchars($form_errors_contact_page['nom'] ?? '') ?></small>
                        </div>
                        <div class="form-group">
                            <label for="contact-email-page">Votre Email : <span class="text-danger">*</span></label>
                            <input type="email" id="contact-email-page" name="email" class="form-control <?= isset($form_errors_contact_page['email']) ? 'input-error' : '' ?>" 
                                   value="<?= htmlspecialchars($email_initial_contact) ?>" required>
                            <small class="form-error-message" id="error-contact-email-page"><?= htmlspecialchars($form_errors_contact_page['email'] ?? '') ?></small>
                        </div>
                        <div class="form-group">
                            <label for="contact-sujet-page">Sujet : <span class="text-danger">*</span></label>
                            <input type="text" id="contact-sujet-page" name="sujet" class="form-control <?= isset($form_errors_contact_page['sujet']) ? 'input-error' : '' ?>" 
                                   value="<?= htmlspecialchars($form_data_contact_page['sujet'] ?? '') ?>" required>
                             <small class="form-error-message" id="error-contact-sujet-page"><?= htmlspecialchars($form_errors_contact_page['sujet'] ?? '') ?></small>
                        </div>
                        <div class="form-group">
                            <label for="contact-message-page">Votre Message : <span class="text-danger">*</span></label>
                            <textarea id="contact-message-page" name="message" class="form-control <?= isset($form_errors_contact_page['message']) ? 'input-error' : '' ?>" 
                                      rows="6" required><?= htmlspecialchars($form_data_contact_page['message'] ?? '') ?></textarea>
                            <small class="form-error-message" id="error-contact-message-page"><?= htmlspecialchars($form_errors_contact_page['message'] ?? '') ?></small>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn primary-action btn-block">
                                <i class="fas fa-paper-plane icon-left"></i>Envoyer le Message
                            </button>
                        </div>
                    </form>
                </div>

                <div class="contact-info-container">
                    <!-- STYLE: margin-bottom inline -->
                    <div class="card" style="margin-bottom: 2rem;"> 
                        <h3 class="card-title"><i class="fas fa-info-circle icon-left"></i>Nos Coordonnées</h3>
                        <p><i class="fas fa-map-marker-alt icon-left"></i><strong>Adresse :</strong><br>123 Rue de la Santé, Ville Principale, Maroc</p>
                        <p><i class="fas fa-phone icon-left"></i><strong>Téléphone :</strong><br><a href="tel:+212656629464" class="link-emphasis">+212 6 56 62 94 64</a></p>
                        <p><i class="fas fa-envelope icon-left"></i><strong>Email :</strong><br><a href="mailto:abdourahamanea9wal@gmail.com" class="link-emphasis">contact@santetv.ma</a></p>
                    </div>
                    <div class="card"> 
                        <h3 class="card-title"><i class="fas fa-clock icon-left"></i>Heures de Support</h3>
                        <p>Lundi - Vendredi : <strong>09h00 - 18h00</strong></p>
                        <p>Samedi : <strong>09h00 - 13h00</strong></p>
                        <!-- STYLE: font-size, margin-top inline -->
                        <p class="text-muted" style="font-size:0.85em; margin-top:1rem;">Nous nous efforçons de répondre à toutes les demandes dans les plus brefs délais.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-section footer-about"><h3 class="footer-title">À propos</h3><p>Notre plateforme connecte les patients aux meilleurs médecins spécialisés.</p></div>
            <div class="footer-section footer-links">
                <h3 class="footer-title">Navigation</h3>
                <ul>
                    <li><a href="../index.php#accueil">Accueil</a></li>
                    <li><a href="docteurs.php">Nos médecins</a></li>
                    <li><a href="../index.php#apropos">À Propos</a></li>
                    <li><a href="contact.php" class="active">Contact</a></li>
                    <li><a href="faq.php">FAQ</a></li> <!-- Changé de faq.html -->
                </ul>
            </div>
                <p><i class="fas fa-envelope icon-left"></i><strong>Email :</strong><br><a href="mailto:abdourahamanea9wal@gmail.com" class="link-emphasis">contact@santetv.ma</a></p>
                <p><i class="fas fa-phone icon-left"></i><a href="tel:+212656629464" class="link-emphasis">+212 6 56 62 94 64</a></p>
            </div>
            <div class="footer-section footer-comment-form">
                <h3 class="footer-title">Votre Avis Compte</h3>
                <form id="commentFormFooterContact" action="../php/soumettre_commentaire.php" method="POST" class="user-form">
                    <input type="hidden" name="form_origin_commentaire" value="../pages/contact.php#commentFormFooterContact">
                    <?= csrf_input_field() ?>
                    <div class="form-group"><label for="nom_commentaire_footer_contact" class="sr-only">Nom</label><input type="text" id="nom_commentaire_footer_contact" name="nom_commentaire" placeholder="Votre nom" required class="form-control"></div>
                    <div class="form-group"><label for="message_commentaire_footer_contact" class="sr-only">Avis</label><textarea id="message_commentaire_footer_contact" name="message_commentaire" placeholder="Votre avis..." required rows="3" class="form-control"></textarea></div>
                    <button type="submit" class="submit-button primary-action btn-sm btn-block">Envoyer Avis</button>
                </form>
            </div>
        </div>
        <div class="footer-social-admin"><div class="social-icons"><a href="#" aria-label="Facebook SANTE TV"><i class="fab fa-facebook-f"></i></a><a href="#" aria-label="Twitter SANTE TV"><i class="fab fa-twitter"></i></a><a href="#" aria-label="Instagram SANTE TV"><i class="fab fa-instagram"></i></a></div><div class="admin-space-link"><a href="admin-login.php">Espace Administrateur</a></div></div>
        <div class="footer-bottom"><p class="copyright-text">© <span id="footer-year"></span> SANTE TV Plus. Tous droits réservés.</p></div>
    </div>
</footer>

<script src="../assets/js/script.js"></script>
</body>
</html>