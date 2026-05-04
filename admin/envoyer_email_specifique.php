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
$csrf_token_admin_email = generate_csrf_token();
$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$nb_medecins_attente_admin_email = 0;
$nb_commentaires_attente_admin_email = 0;
try {
    if ($pdo->query("SHOW TABLES LIKE 'medecins'")->rowCount() > 0) {
        $nb_medecins_attente_admin_email = $pdo->query("SELECT COUNT(*) FROM medecins WHERE valide = 0")->fetchColumn();
    }
    if ($pdo->query("SHOW TABLES LIKE 'commentaires'")->rowCount() > 0) {
        $nb_commentaires_attente_admin_email = $pdo->query("SELECT COUNT(*) FROM commentaires WHERE statut = 'en attente'")->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Erreur récupération badges pour admin_send_email: " . $e->getMessage());
}

$form_data = $_SESSION['form_data_admin_email_spec'] ?? [];
$form_errors = $_SESSION['form_errors_admin_email_spec'] ?? [];
// $selected_users_ids_for_repopulation contient des arrays ['type' => ..., 'id' => ...]
$selected_users_ids_for_repopulation = $_SESSION['selected_users_ids_admin_email_spec'] ?? [];
unset($_SESSION['form_data_admin_email_spec'], $_SESSION['form_errors_admin_email_spec'], $_SESSION['selected_users_ids_admin_email_spec']);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envoyer un Email Spécifique - Administration SANTE TV</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tiny.cloud/1/4amskh9mj3cii8jraizm25oi6k5kvplfgmzr48dm2b52fgj7/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
      tinymce.init({
        selector: 'textarea#message_email',
        plugins: 'lists link image charmap preview anchor pagebreak searchreplace wordcount visualblocks code fullscreen insertdatetime media table help',
        toolbar: 'undo redo | styles | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media | forecolor backcolor emoticons | preview fullscreen | help',
        menubar: 'file edit view insert format tools table help',
        height: 300, 
        language: 'fr_FR',
        // language_url: '../assets/js/tinymce_langs/fr_FR.js' // Assurez-vous que ce chemin est correct si vous l'utilisez
      });
    </script>
    <style>
        .search-results-dropdown {
            border: 1px solid #ddd;
            max-height: 200px;
            overflow-y: auto;
            background-color: white;
            position: absolute;
            width: calc(100% - 2px); /* Pour ne pas dépasser l'input à cause de la bordure */
            z-index: 1000;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .search-results-dropdown ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        .search-results-dropdown li {
            padding: 8px 12px;
            cursor: default; /* Le clic se fait sur le bouton Ajouter */
            border-bottom: 1px solid #eee;
            display: flex; /* Pour aligner le texte et le bouton */
            justify-content: space-between; /* Espace entre texte et bouton */
            align-items: center; /* Centrer verticalement */
        }
        .search-results-dropdown li:last-child {
            border-bottom: none;
        }
        .search-results-dropdown li:hover {
            background-color: #f0f0f0;
        }
        .search-results-dropdown .add-user-btn {
            padding: 4px 8px;
            font-size: 0.8em;
            cursor: pointer;
            background-color: var(--color-success);
            color: white;
            border: none;
            border-radius: var(--border-radius-sm);
        }
        .search-results-dropdown .add-user-btn:hover {
            background-color: var(--color-success-dark);
        }

        .selected-users-area {
            margin-top: 10px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #eee;
            border-radius: var(--border-radius-md);
            min-height: 40px; /* Pour qu'elle soit visible même vide */
        }
        .selected-user-tag {
            display: inline-flex; /* Important pour l'alignement avec le bouton de suppression */
            align-items: center;
            background-color: var(--color-brand-primary); /* Couleur de fond du tag */
            color: var(--text-color-on-brand); /* Couleur du texte dans le tag */
            padding: 5px 8px 5px 10px; /* Ajuster padding pour l'esthétique */
            margin-right: 5px;
            margin-bottom: 5px;
            border-radius: var(--border-radius-sm);
            font-size: 0.9em;
        }
        .selected-user-tag .remove-user {
            margin-left: 8px;
            color: var(--text-color-on-brand); /* Couleur de l'icône de suppression */
            cursor: pointer;
            font-weight: bold;
            font-size: 1.1em; /* Rendre la croix un peu plus grande */
            line-height: 1; /* Mieux centrer la croix */
        }
        .selected-user-tag .remove-user:hover {
            color: var(--color-danger); /* Couleur au survol */
        }
        .form-group-relative { /* Pour que le dropdown se positionne bien */
            position: relative;
        }
    </style>
</head>
<body class="admin-gestion-page body-admin-send-email-specifique"> 

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
                <li><a href="gestion_medecins.php" class="nav-link">Médecins <?php if($nb_medecins_attente_admin_email > 0): ?><span class="badge-notification"><?= $nb_medecins_attente_admin_email ?></span><?php endif; ?></a></li>
                <li><a href="gestion_patients.php" class="nav-link">Patients</a></li>
                <li><a href="envoyer_emails_masse.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'envoyer_emails_masse.php') ? 'active' : ''; ?>"> <i class="fas fa-mail-bulk icon-left"></i>Email en Masse</a></li>
                <li><a href="envoyer_email_specifique.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'envoyer_email_specifique.php') ? 'active' : ''; ?>">Email Spécifique</a></li>
                <li><a href="deconnexion_admin.php" class="nav-link btn btn-sm btn-danger">Déconnexion</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content admin-page-container section-padding"> 
    <div class="container">
        <div class="page-header">
            <h1 class="page-main-title"><i class="fas fa-envelope-open-text page-icon"></i> Envoyer un Email à un/des Utilisateur(s) Spécifique(s)</h1>
        </div>

        <?php if ($flash_message): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type) ?> alert-dismissible">
                <?= $flash_message // Pas besoin de htmlspecialchars car le message est généré par le serveur et est sûr ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button> 
            </div>
        <?php endif; ?>
        
        <?php if (!empty($form_errors) && isset($form_errors['_general'])): ?>
            <div class="alert alert-danger alert-dismissible">
                <?= htmlspecialchars($form_errors['_general']) ?>
                <button type="button" class="close-alert" data-dismiss="alert">×</button>
            </div>
        <?php endif; ?>


        <div class="card" style="padding: var(--spacing-xl);">
            <form action="traitement_admin_send_email.php" method="POST" class="user-form" id="sendSpecificEmailForm">
                <?= csrf_input_field() ?>
                
                <div class="form-group">
                    <label for="user_type_search_email_spec">Type d'utilisateur à rechercher : <span class="text-danger">*</span></label>
                    <select id="user_type_search_email_spec" class="form-control <?= isset($form_errors['user_type_search']) ? 'input-error' : '' ?>">
                        <option value="patient" <?= (isset($form_data['user_type_search']) && $form_data['user_type_search'] === 'patient' || !isset($form_data['user_type_search']) ) ? 'selected' : '' ?>>Patient</option>
                        <option value="medecin" <?= (isset($form_data['user_type_search']) && $form_data['user_type_search'] === 'medecin') ? 'selected' : '' ?>>Médecin</option>
                    </select>
                    <small class="form-error-message"><?= htmlspecialchars($form_errors['user_type_search'] ?? '') ?></small>
                </div>

                <div class="form-group form-group-relative">
                    <label for="user_search_input_email_spec">Rechercher un destinataire :</label>
                    <input type="search" id="user_search_input_email_spec" class="form-control" 
                           placeholder="Tapez un nom, prénom ou email..." autocomplete="off">
                    <div id="search_results_dropdown_email_spec" class="search-results-dropdown" style="display:none;">
                        <ul></ul>
                    </div>
                    <small class="form-note">Commencez à taper pour voir les suggestions. Cliquez sur "Ajouter" pour sélectionner.</small>
                </div>

                <div class="form-group">
                    <label>Destinataires sélectionnés :</label>
                    <div id="selected_users_display_email_spec" class="selected-users-area">
                        <?php if (!empty($selected_users_ids_for_repopulation)): ?>
                            <?php foreach ($selected_users_ids_for_repopulation as $selected_user_data): ?>
                                <?php 
                                // $selected_user_data est un array ['type' => ..., 'id' => ...]
                                $table_repop = $selected_user_data['type'] === 'patient' ? 'patients' : 'medecins';
                                $display_name_repop = 'Utilisateur inconnu';
                                $user_email_repop = '';
                                try {
                                    // Assurez-vous que la table existe avant de la requêter
                                    if($pdo->query("SHOW TABLES LIKE '$table_repop'")->rowCount() > 0) {
                                        $stmt_repop = $pdo->prepare("SELECT email, nom, prenom FROM $table_repop WHERE id = ?");
                                        $stmt_repop->execute([$selected_user_data['id']]);
                                        $user_details_repop = $stmt_repop->fetch();
                                        if ($user_details_repop) {
                                            $display_name_repop = htmlspecialchars(trim($user_details_repop['prenom'] . ' ' . $user_details_repop['nom']));
                                            $user_email_repop = htmlspecialchars($user_details_repop['email']);
                                        }
                                    } else {
                                        error_log("Table '$table_repop' non trouvée pour repopulation tag (admin/envoyer_email_specifique.php)");
                                    }
                                } catch (PDOException $e) {
                                    error_log("Erreur repopulation user tag (admin/envoyer_email_specifique.php): " . $e->getMessage());
                                }
                                ?>
                                <span class="selected-user-tag" data-user-id="<?= htmlspecialchars($selected_user_data['type'] . ':' . $selected_user_data['id']) ?>">
                                    <?= $display_name_repop . (!empty($user_email_repop) ? ' <' . $user_email_repop . '>' : '') ?>
                                    <span class="remove-user" title="Retirer">×</span>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div id="selected_user_ids_hidden_inputs_email_spec">
                        <?php if (!empty($selected_users_ids_for_repopulation)): ?>
                            <?php foreach ($selected_users_ids_for_repopulation as $selected_user_data): ?>
                                <input type="hidden" name="selected_user_ids[]" value="<?= htmlspecialchars($selected_user_data['type'] . ':' . $selected_user_data['id']) ?>">
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <small class="form-error-message" id="error_selected_users"><?= htmlspecialchars($form_errors['selected_user_ids'] ?? '') ?></small>
                </div>


                <div class="form-group">
                    <label for="sujet_email_spec">Sujet : <span class="text-danger">*</span></label>
                    <input type="text" name="sujet" id="sujet_email_spec" class="form-control <?= isset($form_errors['sujet']) ? 'input-error' : '' ?>" 
                           value="<?= htmlspecialchars($form_data['sujet'] ?? '') ?>" required
                           placeholder="Sujet de votre message">
                    <small class="form-error-message"><?= htmlspecialchars($form_errors['sujet'] ?? '') ?></small>
                </div>

                <div class="form-group">
                    <label for="message_email">Message : <span class="text-danger">*</span></label>
                     <p class="form-note">Vous pouvez utiliser `%NOM_UTILISATEUR%` qui sera remplacé par le nom complet du destinataire (ex: "Bonjour %NOM_UTILISATEUR%,").</p>
                    <textarea name="message" id="message_email" rows="10" class="form-control <?= isset($form_errors['message']) ? 'input-error' : '' ?>"><?= htmlspecialchars($form_data['message'] ?? '') ?></textarea>
                    <small class="form-error-message"><?= htmlspecialchars($form_errors['message'] ?? '') ?></small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn primary-action btn-lg">
                        <i class="fas fa-paper-plane icon-left"></i>Envoyer l'Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<footer class="site-footer admin-footer">
   <div class="container">
        <p class="copyright-text text-center">© <span id="footer-year"><?= date('Y') ?></span> SANTE TV - Espace Administration.</p>
    </div>
</footer>

<script src="../assets/js/script.js"></script> 
<!-- Le script JS spécifique à cette page est dans script.js, il sera appelé par initAdminSendSpecificEmailPage() -->
</body>
</html>