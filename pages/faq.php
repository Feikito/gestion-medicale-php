<?php
session_start(); // Nécessaire pour que le header dynamique fonctionne
// csrf_utils.php est requis pour le formulaire de commentaire dans le footer
require_once __DIR__ . '/../php/utils/csrf_utils.php'; 
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - Questions Fréquemment Posées - SANTE TV</title>
    <meta name="description" content="Trouvez les réponses aux questions les plus fréquentes sur SANTE TV : prise de rendez-vous, inscription, sécurité des données et plus.">
    <!-- Chemins relatifs à partir de pages/ -->
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body class="body-page-faq"> 

<header class="site-header">
    <div class="container">
        <div class="logo-branding">
            <!-- Lien vers index.php à la racine -->
            <a href="../index.php" title="SANTE TV Accueil"><img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img"><span class="site-title">SANTE TV</span></a>
        </div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav">
            <span></span><span></span><span></span>
        </button>
        <nav class="main-navigation" id="main-nav">
            <ul>
                <!-- Liens vers index.php à la racine -->
                <li><a href="../index.php#accueil" class="nav-link">ACCUEIL</a></li>
                <li><a href="docteurs.php" class="nav-link">NOS MEDECINS</a></li>
                <li><a href="../index.php#modal-form" class="nav-link js-open-modal-index-from-other-page">REJOIGNEZ NOUS</a></li>
                <li><a href="../index.php#apropos" class="nav-link">A PROPOS</a></li>
                <li><a href="contact.php" class="nav-link">CONTACT</a></li> 
                <li><a href="faq.php" class="nav-link active">FAQ</a></li>
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
    <section class="section-padding faq-page-section">
        <div class="container">
            <div class="page-header text-center">
                <h1 class="page-main-title">Questions Fréquemment Posées (FAQ)</h1>
                <p class="section-subtitle">Trouvez rapidement les réponses à vos interrogations les plus courantes concernant notre plateforme et nos services.</p>
            </div>

            <div class="faq-list" id="faqList">
                <div class="faq-item card"> 
                    <h3 class="faq-question">
                        <span>Comment puis-je prendre un rendez-vous ?</span>
                        <span class="icon"><i class="fas fa-plus"></i></span>
                    </h3>
                    <div class="faq-answer">
                        <p>Pour prendre un rendez-vous, parcourez notre liste de médecins via la section "Nos Médecins" (accessible depuis le menu ou la page d'accueil) ou utilisez la barre de recherche sur la page d'accueil pour trouver un spécialiste. Une fois sur la page de listing des médecins, vous pourrez cliquer sur "Voir le profil" pour accéder à la page détaillée du médecin. Sur cette page, ou directement sur la page de prise de rendez-vous dédiée, vous pourrez consulter ses disponibilités, sélectionner un créneau qui vous convient, et suivre les étapes pour confirmer votre demande. Vous serez notifié(e) par email et sur votre espace patient de la confirmation du médecin.</p>
                    </div>
                </div>
                <div class="faq-item card">
                    <h3 class="faq-question">
                        <span>Puis-je annuler ou modifier un rendez-vous ?</span>
                        <span class="icon"><i class="fas fa-plus"></i></span>
                    </h3>
                    <div class="faq-answer">
                        <p>Oui, l'annulation d'un rendez-vous est possible depuis votre espace patient, dans la section "Mes Rendez-vous". Nous vous encourageons à annuler au moins 24 heures à l'avance pour permettre au médecin de proposer le créneau à un autre patient. Un motif d'annulation vous sera demandé.</p>
                        <p>Pour modifier un rendez-vous (changer la date ou l'heure), la procédure actuelle est d'annuler votre rendez-vous existant, puis de prendre un nouveau rendez-vous avec les informations souhaitées. Assurez-vous de vérifier les disponibilités avant d'annuler.</p>
                    </div>
                </div>
                <div class="faq-item card">
                    <h3 class="faq-question">
                        <span>Comment m'inscrire en tant que médecin sur SANTE TV ?</span>
                        <span class="icon"><i class="fas fa-plus"></i></span>
                    </h3>
                    <div class="faq-answer">
                        <p>Nous sommes ravis de vous accueillir parmi nos professionnels partenaires ! Pour vous inscrire, cliquez sur le lien "Rejoignez Nous" présent dans le menu de navigation en haut de page. Vous serez invité(e) à remplir un formulaire détaillé avec vos informations professionnelles (nom, spécialité, adresse du cabinet, etc.) et à télécharger des documents justificatifs (comme votre CV, diplômes, attestation d'inscription à l'ordre des médecins). Votre demande sera ensuite examinée par notre équipe administrative. Une fois validée, vous recevrez une notification par email et pourrez accéder à votre espace médecin pour configurer votre profil, vos disponibilités et commencer à recevoir des demandes de rendez-vous.</p>
                    </div>
                </div>
                <div class="faq-item card">
                    <h3 class="faq-question">
                        <span>Mes informations personnelles et médicales sont-elles sécurisées ?</span>
                        <span class="icon"><i class="fas fa-plus"></i></span>
                    </h3>
                    <div class="faq-answer">
                        <p>Absolument. La sécurité et la confidentialité de vos données sont notre priorité absolue. SANTE TV utilise des protocoles de sécurité standards de l'industrie, incluant le cryptage des données sensibles (comme les mots de passe, qui sont hachés et salés) et des connexions sécurisées (HTTPS). Nous nous engageons à protéger vos informations personnelles et médicales conformément aux réglementations en vigueur. Pour plus de détails, nous vous invitons à consulter notre Politique de Confidentialité (lien disponible en bas de page).</p>
                    </div>
                </div>
                 <div class="faq-item card">
                    <h3 class="faq-question">
                        <span>Le service de prise de rendez-vous est-il payant pour les patients ?</span>
                        <span class="icon"><i class="fas fa-plus"></i></span>
                    </h3>
                    <div class="faq-answer">
                        <p>Non, l'utilisation de la plateforme SANTE TV pour rechercher un médecin et prendre un rendez-vous est entièrement gratuite pour les patients. Les honoraires de consultation sont à régler directement auprès du professionnel de santé que vous consultez, selon ses propres tarifs et modalités de paiement. SANTE TV n'intervient pas dans la transaction financière de la consultation.</p>
                    </div>
                </div>
                <div class="faq-item card">
                    <h3 class="faq-question">
                        <span>Que faire si j'ai oublié mon mot de passe ?</span>
                        <span class="icon"><i class="fas fa-plus"></i></span>
                    </h3>
                    <div class="faq-answer">
                        <p>Si vous avez oublié votre mot de passe, rendez-vous sur la page de connexion (ou ouvrez la modale de connexion) et cliquez sur le lien "Mot de passe oublié ?". Vous devrez ensuite renseigner l'adresse e-mail associée à votre compte (patient ou médecin). Si un compte correspondant est trouvé, un email contenant un lien sécurisé et temporaire vous sera envoyé. En cliquant sur ce lien, vous pourrez définir un nouveau mot de passe pour accéder à votre compte.</p>
                    </div>
                </div>
                 <div class="faq-item card">
                    <h3 class="faq-question">
                        <span>Comment puis-je contacter le support de SANTE TV ?</span>
                        <span class="icon"><i class="fas fa-plus"></i></span>
                    </h3>
                    <div class="faq-answer">
                        <p>Si vous avez besoin d'assistance ou si vous avez des questions qui ne sont pas couvertes par cette FAQ, vous pouvez nous contacter via le formulaire disponible sur notre page "Contact" (lien dans le menu ou le pied de page). Vous y trouverez également nos coordonnées email et téléphoniques. Notre équipe s'efforcera de vous répondre dans les meilleurs délais.</p>
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
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="faq.php" class="active">FAQ</a></li>
                </ul>
            </div>
            <div class="footer-section footer-contact"><h3 class="footer-title">Nous Joindre</h3><p><i class="fas fa-envelope icon-left"></i><a href="mailto:contact@santetv.ma">contact@santetv.ma</a></p><p><i class="fas fa-phone icon-left"></i><a href="tel:+212656629464">+212 6 56 62 94 64</a></p></div>
            <div class="footer-section footer-comment-form">
                <h3 class="footer-title">Votre Avis Compte</h3>
                <form id="commentFormFooterFAQ" action="../php/soumettre_commentaire.php" method="POST" class="user-form">
                    <input type="hidden" name="form_origin_commentaire" value="../pages/faq.php#commentFormFooterFAQ">
                    <?= csrf_input_field() ?>
                    <div class="form-group"><label for="nom_commentaire_footer_faq" class="sr-only">Nom</label><input type="text" id="nom_commentaire_footer_faq" name="nom_commentaire" placeholder="Votre nom" required class="form-control"></div>
                    <div class="form-group"><label for="message_commentaire_footer_faq" class="sr-only">Avis</label><textarea id="message_commentaire_footer_faq" name="message_commentaire" placeholder="Votre avis..." required rows="3" class="form-control"></textarea></div>
                    <button type="submit" class="submit-button primary-action btn-sm btn-block">Envoyer Avis</button>
                </form>
            </div>
        </div>
        <div class="footer-social-admin"><div class="social-icons"><a href="#" aria-label="Facebook SANTE TV"><i class="fab fa-facebook-f"></i></a><a href="#" aria-label="Twitter SANTE TV"><i class="fab fa-twitter"></i></a><a href="#" aria-label="Instagram SANTE TV"><i class="fab fa-instagram"></i></a></div><div class="admin-space-link"><a href="admin-login.php">Espace Administrateur</a></div></div>
        <div class="footer-bottom"><p class="copyright-text">© <span id="footer-year"></span> SANTE TV Plus. Tous droits réservés.</p></div>
    </div>
</footer>

<script src="../assets/js/script.js"></script>
<!-- Le JS pour l'accordéon FAQ (ciblant .faq-question) et les liens js-open-modal-index-from-other-page est dans script.js -->
</body>
</html>