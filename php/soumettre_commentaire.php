<?php
session_start(); 
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 
// require_once __DIR__ . '/utils/email_functions.php'; // Décommentez si vous notifiez l'admin

// Déterminer l'origine du formulaire pour la redirection
// La valeur de 'form_origin_commentaire' devrait être comme "index.php#commentFormFooter" 
// ou "../pages/docteurs.php#commentFormFooterDoctors", etc.
$form_origin_posted = $_POST['form_origin_commentaire'] ?? 'index.php'; // Fallback à index.php à la racine

// Construire l'URL de redirection correcte en ajoutant ../ si nécessaire
// Si l'origine est une page dans 'pages/', le chemin relatif est déjà correct (ex: ../pages/contact.php)
// Si l'origine est 'index.php', il faut remonter de 'php/' vers la racine.
if (strpos($form_origin_posted, 'index.php') === 0) { // Commence par index.php (donc à la racine)
    $redirect_url = '../' . $form_origin_posted;
} elseif (strpos($form_origin_posted, '../pages/') === 0) { // Vient d'une page dans ../pages/
    $redirect_url = $form_origin_posted; // Le chemin est déjà correct
} else {
    // Fallback général, si le chemin n'est pas reconnu, on redirige vers l'index à la racine.
    $redirect_url = '../index.php';
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect_url); 
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $_SESSION['flash_message'] = "Erreur de sécurité lors de la soumission de votre commentaire. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $redirect_url); 
    exit;
}

$nom_auteur_commentaire = trim($_POST['nom_commentaire'] ?? '');
$contenu_commentaire = trim($_POST['message_commentaire'] ?? '');
// $email_auteur_commentaire = trim(strtolower($_POST['email_commentaire'] ?? '')); // Si vous ajoutez un champ email

$errors_commentaire = [];

if (empty($nom_auteur_commentaire)) {
    $errors_commentaire['nom'] = "Votre nom est requis pour laisser un commentaire.";
} elseif (strlen($nom_auteur_commentaire) > 100) {
    $errors_commentaire['nom'] = "Votre nom ne doit pas dépasser 100 caractères.";
}

if (empty($contenu_commentaire)) {
    $errors_commentaire['contenu'] = "Veuillez écrire le contenu de votre commentaire.";
} elseif (strlen($contenu_commentaire) < 10) { 
    $errors_commentaire['contenu'] = "Votre commentaire doit contenir au moins 10 caractères.";
} elseif (strlen($contenu_commentaire) > 1000) { 
    $errors_commentaire['contenu'] = "Votre commentaire ne doit pas dépasser 1000 caractères.";
}

if (!empty($errors_commentaire)) {
    $error_summary = "Erreurs lors de la soumission de votre commentaire :<br>" . implode("<br>", array_values($errors_commentaire));
    $_SESSION['flash_message'] = $error_summary;
    $_SESSION['flash_type'] = "error";
    // Optionnel: Stocker les données du formulaire pour les réafficher
    // $_SESSION['form_data_comment'] = $_POST; 
    header("Location: " . $redirect_url); 
    exit;
}

try {
    // S'assurer que la table 'commentaires' existe et a les bonnes colonnes
    $table_commentaires_exists = $pdo->query("SHOW TABLES LIKE 'commentaires'")->rowCount() > 0;
    if (!$table_commentaires_exists) {
        throw new PDOException("La table 'commentaires' est manquante.");
    }
    // Vérifier si la colonne 'email' existe si vous prévoyez de l'utiliser
    // $comment_cols = $pdo->query("DESCRIBE commentaires")->fetchAll(PDO::FETCH_COLUMN);
    // $has_email_col = in_array('email', $comment_cols);

    $sql_insert_commentaire = "INSERT INTO commentaires (nom, contenu, statut) VALUES (:nom, :contenu, 'en attente')";
    $params_insert = [
        ':nom' => $nom_auteur_commentaire, 
        ':contenu' => $contenu_commentaire
    ];
    // Si vous avez un champ email et la colonne dans la BDD:
    // $sql_insert_commentaire = "INSERT INTO commentaires (nom, email, contenu, statut) VALUES (:nom, :email, :contenu, 'en attente')";
    // $params_insert[':email'] = !empty($email_auteur_commentaire) ? $email_auteur_commentaire : null;


    $stmt_insert_commentaire = $pdo->prepare($sql_insert_commentaire);
    $stmt_insert_commentaire->execute($params_insert);

    $admin_email_notif_com = defined('ADMIN_EMAIL_NOTIFICATIONS') ? ADMIN_EMAIL_NOTIFICATIONS : null;
    if ($admin_email_notif_com && function_exists('envoyer_email')) {
        $sujet_admin_com = "Nouveau commentaire en attente de validation sur SANTE TV";
        $corps_html_admin_com = "<h1>Nouveau Commentaire Soumis</h1>
                               <p>Un nouveau commentaire a été soumis et est en attente de votre validation :</p>
                               <ul>
                                   <li>Auteur: " . htmlspecialchars($nom_auteur_commentaire) . "</li>
                                   <li>Commentaire (extrait): " . nl2br(htmlspecialchars(mb_strimwidth($contenu_commentaire, 0, 150, "..."))) . "</li>
                               </ul>
                               <p>Veuillez vous connecter à l'espace administration pour le modérer.</p>";
        // envoyer_email($admin_email_notif_com, "Admin SANTE TV", $sujet_admin_com, $corps_html_admin_com);
    }

    $_SESSION['flash_message'] = "Merci pour votre commentaire ! Il sera affiché sur le site après validation par notre équipe.";
    $_SESSION['flash_type'] = "success";
    header("Location: " . $redirect_url); 
    exit;

} catch (PDOException $e) {
    error_log("Erreur PDO soumettre_commentaire.php: " . $e->getMessage());
    $_SESSION['flash_message'] = "Une erreur technique est survenue lors de la soumission de votre commentaire. Veuillez réessayer.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $redirect_url);
    exit;
}
?>