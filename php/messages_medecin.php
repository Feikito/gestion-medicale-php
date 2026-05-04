<?php
session_start();
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 

if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'medecin') {
    $_SESSION['flash_message_login'] = "Accès non autorisé.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/connexion.php'); 
    exit;
}
$medecin_id_msg_page = $_SESSION['utilisateur_id'];

$stmt_medecin_info_msg = $pdo->prepare("SELECT id, nom, prenom, email, valide FROM medecins WHERE id = ?");
$stmt_medecin_info_msg->execute([$medecin_id_msg_page]);
$medecin_data_msg = $stmt_medecin_info_msg->fetch(PDO::FETCH_ASSOC);

if (!$medecin_data_msg) { 
    session_unset(); session_destroy();
    header('Location: ../pages/connexion.php'); exit;
}
$nom_medecin_display_msg_header = htmlspecialchars("Dr. " . ($medecin_data_msg['prenom'] ?? '') . ' ' . ($medecin_data_msg['nom'] ?? 'Médecin'));
$_SESSION['medecin_email_for_reply'] = $medecin_data_msg['email']; 
$_SESSION['medecin_nom_for_reply'] = $nom_medecin_display_msg_header;


$messages_recus_par_medecin_list = [];
$colonne_lu_par_medecin_existe = false;
$table_messages_exist_check_msg = $pdo->query("SHOW TABLES LIKE 'messages'")->rowCount() > 0;

if ($table_messages_exist_check_msg) {
    try {
        $check_col_stmt_msg_lu = $pdo->query("SHOW COLUMNS FROM messages LIKE 'lu_par_medecin'");
        if ($check_col_stmt_msg_lu->fetch()) {
            $colonne_lu_par_medecin_existe = true;
        }

        $sql_get_messages = "SELECT 
                                msg.id as message_id, msg.contenu, msg.date_envoi, msg.patient_id, 
                                msg.sujet_message,
                                p.nom AS patient_nom, p.prenom AS patient_prenom,
                                p.email AS patient_email, p.photo AS patient_photo";
        if ($colonne_lu_par_medecin_existe) {
            $sql_get_messages .= ", msg.lu_par_medecin ";
        }
        $sql_get_messages .= " FROM messages msg
                               JOIN patients p ON msg.patient_id = p.id 
                               WHERE msg.destinataire_id = :medecin_id 
                               ORDER BY msg.date_envoi DESC";

        $stmt_messages_recus = $pdo->prepare($sql_get_messages);
        $stmt_messages_recus->execute([':medecin_id' => $medecin_id_msg_page]);
        $messages_recus_par_medecin_list = $stmt_messages_recus->fetchAll(PDO::FETCH_ASSOC);

        if ($colonne_lu_par_medecin_existe && count($messages_recus_par_medecin_list) > 0) {
            $ids_messages_a_marquer_lus_med = [];
            foreach ($messages_recus_par_medecin_list as $msg_recu_item) {
                if (isset($msg_recu_item['lu_par_medecin']) && !$msg_recu_item['lu_par_medecin']) {
                    $ids_messages_a_marquer_lus_med[] = $msg_recu_item['message_id'];
                }
            }
            if (!empty($ids_messages_a_marquer_lus_med)) {
                $in_clause_ids_msg_med = implode(',', array_fill(0, count($ids_messages_a_marquer_lus_med), '?'));
                $stmt_mark_lu_msg_med = $pdo->prepare("UPDATE messages SET lu_par_medecin = 1 WHERE id IN ($in_clause_ids_msg_med) AND destinataire_id = ?");
                $params_mark_lu_msg_med = array_merge($ids_messages_a_marquer_lus_med, [$medecin_id_msg_page]);
                $stmt_mark_lu_msg_med->execute($params_mark_lu_msg_med);
            }
        }
    } catch (PDOException $e) {
        error_log("Erreur récupération/marquage messages médecin $medecin_id_msg_page: " . $e->getMessage());
    }
} else {
     error_log("Table 'messages' non trouvée pour médecin $medecin_id_msg_page.");
}

$flash_message_msg_med_page = $_SESSION['flash_message'] ?? null;
$flash_type_msg_med_page = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$stmt_rdv_att_nav_msg_med = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE medecin_id = :id AND statut = 'en attente' AND ( (date_rdv > CURDATE()) OR (date_rdv = CURDATE() AND heure_rdv >= CURTIME()) ) AND supprime_par_medecin = 0");
$stmt_rdv_att_nav_msg_med->execute([':id' => $medecin_id_msg_page]);
$nb_rdv_att_nav_msg_med = $stmt_rdv_att_nav_msg_med->fetchColumn();
$nb_msg_nav_display_badge_med = 0; 

$csrf_token_actions_msg = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie Reçue - <?= $nom_medecin_display_msg_header ?> - SANTE TV</title>
    <meta name="description" content="Consultez les messages envoyés par vos patients via la plateforme SANTE TV.">
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tiny.cloud/1/4amskh9mj3cii8jraizm25oi6k5kvplfgmzr48dm2b52fgj7/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body class="user-dashboard-page body-page-messages-medecin"> 

<header class="site-header">
    <div class="container">
        <div class="logo-branding"><a href="../index.php" title="Accueil SANTE TV"><img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img"><span class="site-title">SANTE TV</span></a></div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav"><span></span><span></span><span></span></button>
        <nav class="main-navigation" id="main-nav">
             <ul>
                <li><a href="espace_medecin.php" class="nav-link">Mon Espace</a></li>
                <li><a href="mes_rendez_vous_medecin.php" class="nav-link">Mes Rendez-vous <?php if($nb_rdv_att_nav_msg_med > 0): ?><span class="badge-notification"><?= $nb_rdv_att_nav_msg_med ?></span><?php endif; ?></a></li>
                <li><a href="gestion_disponibilites_medecin.php" class="nav-link">Mes Disponibilités</a></li>
                <li>
                    <a href="messages_medecin.php" class="nav-link active">Messagerie Reçue
                        <?php if($nb_msg_nav_display_badge_med > 0): ?>
                        <span class="badge-notification"><?= $nb_msg_nav_display_badge_med ?></span>
                        <?php endif; ?>
                    </a>
                </li>                
                <li><a href="profil_medecin.php" class="nav-link">Mon Profil</a></li>
                <li><a href="deconnexion.php" class="nav-link btn btn-sm btn-danger">Déconnexion</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content section-padding">
    <div class="container">
        <div class="page-header">
            <h1 class="page-main-title">Messagerie : Messages Reçus des Patients</h1>
             <a href="espace_medecin.php" class="btn btn-sm secondary-action">← Retour à Mon Espace</a>
        </div>

        <?php if ($flash_message_msg_med_page): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_msg_med_page) ?> alert-dismissible">
                <?= $flash_message_msg_med_page ?>
                <button type="button" class="close-alert" data-dismiss="alert">×</button>
            </div>
        <?php endif; ?>

        <?php if ($medecin_data_msg['valide'] != 1): ?>
             <div class="alert alert-warning" style="margin-top:1.5rem;">Votre compte est en attente de validation. Vous pouvez consulter vos messages, mais certaines interactions peuvent être limitées.</div>
        <?php endif; ?>
        
        <section class="messages-section card" style="margin-top:1.5rem;"> 
            <h2 class="section-title card-title" style="display: none;">Boîte de Réception</h2>
            <?php if (count($messages_recus_par_medecin_list) > 0): ?>
                <ul class="messages-list">
                    <?php foreach ($messages_recus_par_medecin_list as $msg_item): ?>
                        <li class="message-item message-received <?= ($colonne_lu_par_medecin_existe && isset($msg_item['lu_par_medecin']) && !$msg_item['lu_par_medecin']) ? 'message-unread-initial' : '' ?>">
                            <div class="message-header">
                                <span class="message-sender">
                                    <img src="<?= $msg_item['patient_photo'] ? '../' . htmlspecialchars($msg_item['patient_photo']) : '../assets/images/placeholder-patient.png' ?>" 
                                         alt="Photo de <?= htmlspecialchars($msg_item['patient_prenom'] . ' ' . $msg_item['patient_nom']) ?>" 
                                         class="table-avatar-img">
                                    De : <strong><?= htmlspecialchars($msg_item['patient_prenom'] . ' ' . $msg_item['patient_nom']) ?></strong>
                                </span>
                                <span class="message-date">
                                    <i class="fas fa-clock icon-left"></i><?= date('d/m/Y \à H:i', strtotime($msg_item['date_envoi'])) ?>
                                </span>
                            </div>
                            <?php if(isset($msg_item['sujet_message']) && !empty($msg_item['sujet_message'])): ?>
                                <div class="message-subject" style="padding: 5px var(--spacing-lg) 0; font-weight: bold; color: var(--color-neutral-dark);">
                                    Sujet : <?= htmlspecialchars($msg_item['sujet_message']) ?>
                                </div>
                            <?php endif; ?>
                            <div class="message-content">
                                <p><?= nl2br(htmlspecialchars($msg_item['contenu'])) ?></p>
                            </div>
                            <div class="message-actions">
                                <button type="button" class="btn btn-sm btn-outline-primary open-reponse-email-modal"
                                        data-patient-id="<?= $msg_item['patient_id'] ?>"
                                        data-patient-email="<?= htmlspecialchars($msg_item['patient_email']) ?>"
                                        data-patient-nom="<?= htmlspecialchars($msg_item['patient_prenom'] . ' ' . $msg_item['patient_nom']) ?>"
                                        data-sujet-original="RE: <?= htmlspecialchars($msg_item['sujet_message'] ?: 'Votre message sur SANTE TV') ?>"
                                        title="Répondre au patient via la plateforme">
                                    <i class="fas fa-reply icon-left"></i>Répondre
                                </button>
                                <form action="supprimer_notification.php" method="POST" style="display:inline; margin-left: 5px;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_actions_msg) ?>">
                                    <input type="hidden" name="notification_id" value="<?= $msg_item['message_id'] ?>">
                                    <input type="hidden" name="user_type" value="medecin_message">
                                    <button type="submit" class="btn btn-xs btn-danger" 
                                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce message ? Cette action est irréversible.');"
                                            title="Supprimer ce message">
                                        <i class="fas fa-trash-alt"></i> Supprimer
                                    </button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php elseif(!$table_messages_exist_check_msg): ?>
                 <p class="no-messages text-center" style="padding: 2rem 0;"><i class="fas fa-exclamation-triangle icon-left"></i>Le système de messagerie est actuellement indisponible.</p>
            <?php else: ?>
                <p class="no-messages text-center" style="padding: 2rem 0;"><i class="fas fa-folder-open icon-left"></i>Votre boîte de réception est vide pour le moment.</p>
            <?php endif; ?>
        </section>
    </div>
</main>

<div id="modalRepondreEmail" class="modal" role="dialog" aria-modal="true" aria-labelledby="titleModalRepondreEmail">
    <div class="modal-content" style="max-width: 700px;">
        <button class="close-modal-button" aria-label="Fermer">×</button>
        <h3 class="form-title" id="titleModalRepondreEmail">Répondre au Patient</h3>
        <form id="formRepondreEmailPatient" action="traitement_reponse_medecin.php" method="POST" class="user-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_actions_msg) ?>">
            <input type="hidden" name="patient_id_destinataire" id="reponsePatientIdDestinataire">
            <input type="hidden" name="patient_email_destinataire" id="reponsePatientEmailDestinataire">
            
            <div class="form-group">
                <label for="reponseDestinataireAffichage">Destinataire :</label>
                <input type="text" id="reponseDestinataireAffichage" class="form-control readonly-input" readonly>
            </div>
            <div class="form-group">
                <label for="reponseSujet">Sujet : <span class="text-danger">*</span></label>
                <input type="text" name="sujet" id="reponseSujet" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="reponseMessage">Votre Message : <span class="text-danger">*</span></label>
                <textarea name="message" id="reponseMessage" rows="10" class="form-control"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn primary-action">
                    <i class="fas fa-paper-plane icon-left"></i>Envoyer la Réponse
                </button>
                 <button type="button" class="btn secondary-action" data-close-modal>Annuler</button>
            </div>
        </form>
    </div>
</div>

<footer class="site-footer">
   <div class="container"><p class="copyright-text text-center">© <span id="footer-year"></span> SANTE TV Plus. Tous droits réservés.</p></div>
</footer>

<script src="../assets/js/script.js"></script> 
</body>
</html>