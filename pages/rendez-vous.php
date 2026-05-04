<?php
session_start();
// Ce fichier (pages/rendez-vous.php) est dans 'pages/'
require_once __DIR__ . '/../php/db.php'; 
require_once __DIR__ . '/../php/utils/csrf_utils.php'; 

// 1. Vérifier si le patient est connecté.
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'patient') {
    $_SESSION['flash_message'] = "Vous devez être connecté en tant que patient pour prendre un rendez-vous.";
    $_SESSION['flash_type'] = "warning"; 
    $_SESSION['redirect_url_after_login'] = $_SERVER['REQUEST_URI']; 
    header('Location: connexion.php'); // Rediriger vers connexion.php dans le même dossier 'pages/'
    exit;
}
$patient_id_session = $_SESSION['utilisateur_id'];
$nom_patient_session = $_SESSION['nom'] ?? 'Patient';

// 2. Récupérer les données et erreurs de la session
$form_data_rdv_page = $_SESSION['form_data_rdv'] ?? [];
$form_errors_rdv_page = $_SESSION['form_errors_rdv'] ?? [];
unset($_SESSION['form_data_rdv'], $_SESSION['form_errors_rdv']);

// 3. Récupérer les paramètres GET
$medecin_id_get = filter_input(INPUT_GET, 'medecin_id', FILTER_VALIDATE_INT);
$date_get_str = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_SPECIAL_CHARS); 
$heure_get_str = filter_input(INPUT_GET, 'heure', FILTER_SANITIZE_SPECIAL_CHARS); 
$medecin_nom_get = htmlspecialchars_decode(filter_input(INPUT_GET, 'medecin_nom', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''); 

// 4. Déterminer les valeurs initiales
$medecin_id_initial_val = $form_data_rdv_page['medecin_id'] ?? $medecin_id_get ?? '';
$date_initial_val = $form_data_rdv_page['date_rdv'] ?? $date_get_str ?? '';
$heure_initial_val_hidden = '';
if (!empty($form_data_rdv_page['heure_rdv'])) {
    $heure_initial_val_hidden = $form_data_rdv_page['heure_rdv'];
} elseif (!empty($heure_get_str) && preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $heure_get_str)) {
    $heure_initial_val_hidden = $heure_get_str . ':00';
}
$medecin_nom_initial_display_val = $medecin_nom_get ?: ($form_data_rdv_page['medecin_nom'] ?? '');


// 5. Récupérer le message flash général
$flash_message_display_page = $_SESSION['flash_message'] ?? null;
$flash_type_display_page = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$medecins_liste_select = [];
try {
    $stmt_all_meds = $pdo->query("SELECT id, nom, prenom, specialite FROM medecins WHERE valide = 1 ORDER BY nom, prenom");
    $medecins_liste_select = $stmt_all_meds->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur récupération liste médecins pour RDV: " . $e->getMessage());
}

$csrf_token_rdv = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prendre un Rendez-vous - SANTE TV</title>
    <!-- Chemins relatifs à partir de pages/ -->
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body class="rdv-page"> 

<header class="site-header">
    <div class="container">
        <div class="logo-branding">
            <!-- Lien vers index.php à la racine -->
            <a href="../index.php"><img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img"><span class="site-title">SANTE TV</span></a>
        </div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav">
            <span></span><span></span><span></span>
        </button>
        <nav class="main-navigation" id="main-nav">
            <ul>
                <!-- Liens vers index.php à la racine -->
                <li><a href="../index.php#accueil" class="nav-link">ACCUEIL</a></li>
                <li><a href="docteurs.php" class="nav-link">NOS MEDECINS</a></li>
                <?php if (isset($_SESSION['utilisateur_id'])): ?>
                    <?php if ($_SESSION['type'] === 'patient'): ?>
                        <li><a href="../php/dashboard_patient.php" class="nav-link btn-header-connect">MON ESPACE</a></li>
                    <?php elseif ($_SESSION['type'] === 'medecin'): ?>
                        <li><a href="../index.php#modal-form" class="nav-link js-open-modal-index-from-other-page">REJOIGNEZ NOUS</a></li>
                        <li><a href="../php/espace_medecin.php" class="nav-link btn-header-connect">MON ESPACE</a></li>
                    <?php else: ?>
                        <li><a href="../index.php#modal-form" class="nav-link js-open-modal-index-from-other-page">REJOIGNEZ NOUS</a></li>
                        <li><a href="../index.php#modal-connexion" class="nav-link btn-header-connect js-open-modal-index-from-other-page">SE CONNECTER</a></li>
                    <?php endif; ?>
                <?php else: ?>
                    <li><a href="../index.php#modal-form" class="nav-link js-open-modal-index-from-other-page">REJOIGNEZ NOUS</a></li>
                    <li><a href="../index.php#modal-connexion" class="nav-link btn-header-connect js-open-modal-index-from-other-page">SE CONNECTER</a></li>
                <?php endif; ?>
                <li><a href="../index.php#apropos" class="nav-link">A PROPOS</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content section-padding">
    <!-- STYLE: max-width inline -->
    <div class="container" style="max-width: 750px;"> 
        <div class="page-header text-center">
            <h1 class="page-main-title">Programmer un Rendez-vous</h1>
            <p class="section-subtitle">
                Vous êtes connecté(e) en tant que <strong><?= htmlspecialchars($nom_patient_session) ?></strong>.
            </p>
        </div>

        <!-- STYLE: display et margin-bottom inline -->
        <div id="form-feedback-general-rdv" 
             class="alert <?= !empty($flash_message_display_page) ? 'alert-' . htmlspecialchars($flash_type_display_page) : '' ?>" 
             style="<?= !empty($flash_message_display_page) ? 'display:block; margin-bottom:1.5rem;' : 'display:none;' ?>">
            <?= htmlspecialchars($flash_message_display_page ?? '') ?>
            <?php if(!empty($flash_message_display_page)): ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button>
            <?php endif; ?>
        </div>

        <!-- Action vers php/prendre_rdv.php (correct) -->
        <form id="formPriseRdv" action="../php/prendre_rdv.php" method="POST" class="user-form rdv-form-box card">
            <?= csrf_input_field() ?>
            <!-- Origine = cette page (pages/rendez-vous.php) -->
            <input type="hidden" name="form_origin_rdv" value="../pages/rendez-vous.php">
            <input type="hidden" id="medecin_nom_rdv_hidden" name="medecin_nom" value="<?= htmlspecialchars($medecin_nom_initial_display_val) ?>">

            <div class="form-group">
                <label for="medecin_id_rdv">Choisissez un médecin : <span class="text-danger">*</span></label>
                <select id="medecin_id_rdv" name="medecin_id" class="form-control <?= isset($form_errors_rdv_page['medecin_id']) ? 'input-error' : '' ?>" required>
                    <option value="">-- Sélectionnez un médecin --</option>
                    <?php foreach ($medecins_liste_select as $med): ?>
                        <option value="<?= $med['id'] ?>" 
                                <?= ($medecin_id_initial_val == $med['id']) ? 'selected' : '' ?>
                                data-nom="Dr. <?= htmlspecialchars($med['prenom'] . ' ' . $med['nom']) ?>">
                            Dr. <?= htmlspecialchars($med['prenom'] . ' ' . $med['nom']) ?> (<?= htmlspecialchars($med['specialite']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-error-message" id="error-medecin_id_rdv"><?= htmlspecialchars($form_errors_rdv_page['medecin_id'] ?? '') ?></small>
            </div>

            <div class="form-group">
                <label for="date_rdv">Date du Rendez-vous : <span class="text-danger">*</span></label>
                <input type="date" id="date_rdv" name="date_rdv" class="form-control <?= isset($form_errors_rdv_page['date_rdv']) ? 'input-error' : '' ?>" 
                       value="<?= htmlspecialchars($date_initial_val) ?>" required 
                       min="<?= date('Y-m-d') ?>"
                       <?= empty($medecin_id_initial_val) ? 'disabled' : '' ?>> 
                <small class="form-error-message" id="error-date_rdv"><?= htmlspecialchars($form_errors_rdv_page['date_rdv'] ?? '') ?></small>
            </div>

            <div class="form-group">
                <!-- STYLE: display inline -->
                <label id="time-slots-label" for="heure_rdv_hidden" style="display:none;">Créneaux Horaires Disponibles : <span class="text-danger">*</span></label>
                <!-- STYLE: display inline -->
                <div id="time-slots-container" style="display:none;">
                    <!-- STYLE: display inline -->
                    <p id="loading-creneaux" class="loading-message info-message" style="display:none;"><i class="fas fa-spinner fa-spin icon-left"></i> Recherche des créneaux disponibles...</p>
                    <div id="time-slots-grid" class="time-slots-grid-style">
                    </div>
                    <!-- STYLE: display inline -->
                    <p id="no-slots-message" class="info-message" style="display:none;">Aucun créneau disponible pour cette date. Veuillez essayer une autre date ou un autre médecin.</p>
                </div>
                <input type="hidden" id="heure_rdv_hidden" name="heure_rdv" value="<?= htmlspecialchars($heure_initial_val_hidden) ?>">
                <!-- STYLE: display et margin-top inline -->
                <small class="form-error-message error-message-display" id="heure_rdv_error_msg" style="display:<?= !empty($form_errors_rdv_page['heure_rdv']) ? 'block' : 'none' ?>; margin-top:5px;">
                    <?= htmlspecialchars($form_errors_rdv_page['heure_rdv'] ?? '') ?>
                </small>
            </div>
            
            <div class="form-actions">
                <button type="submit" id="submitRdvButton" class="btn primary-action btn-block" 
                        <?= (empty($medecin_id_initial_val) || empty($date_initial_val) || empty($heure_initial_val_hidden)) ? 'disabled' : '' ?>>
                    <i class="fas fa-check-circle icon-left"></i>Confirmer le Rendez-vous
                </button>
            </div>
        </form>
    </div>
</main>

<footer class="site-footer">
    <div class="container">
        <p class="copyright-text text-center">© <span id="footer-year"></span> SANTE TV Plus. Tous droits réservés.</p>
    </div>
</footer>

<script src="../assets/js/script.js"></script>
<script>
    const rdvPageInitialData = {
        medecinId: <?= json_encode($medecin_id_initial_val) ?>,
        date: <?= json_encode($date_initial_val) ?>,
        heure: <?= json_encode($heure_initial_val_hidden) ?>,
        medecinNom: <?= json_encode($medecin_nom_initial_display_val) ?>
    };
    const medecinsListForSelectRdvPage = <?= json_encode($medecins_liste_select) ?>;
    // Le script JS pour gérer la logique de cette page (chargement des créneaux, etc.)
    // devrait être conditionné par la présence de la classe .rdv-page sur le body dans script.js
    // et utiliser les constantes rdvPageInitialData et medecinsListForSelectRdvPage.
    // Les liens js-open-modal-index-from-other-page nécessitent la logique de redirection expliquée précédemment.
</script>

</body>
</html>