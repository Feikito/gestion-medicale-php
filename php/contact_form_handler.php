<?php
session_start();
require 'db.php'; // S'assure que db.php est dans le même dossier (php/)
require_once __DIR__ . '/utils/csrf_utils.php';
require_once __DIR__ . '/utils/email_functions.php'; // Pour envoyer l'email

// Récupérer l'origine du formulaire pour la redirection
// La valeur devrait être comme "../pages/contact.php"
$form_origin_contact = $_POST['form_origin_contact'] ?? '../pages/contact.php'; // Fallback

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $form_origin_contact);
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) {
    $_SESSION['flash_message'] = "Erreur de sécurité lors de la soumission du message. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $form_origin_contact);
    exit;
}

$nom_expediteur = trim(strip_tags($_POST['nom'] ?? ''));
$email_expediteur = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
$sujet_contact = trim(strip_tags($_POST['sujet'] ?? ''));
$message_contact = trim(strip_tags($_POST['message'] ?? '')); // Simple strip_tags, pour du HTML, utilisez une librairie de purification

$_SESSION['form_data_contact'] = $_POST; // Pour pré-remplir en cas d'erreur
$_SESSION['form_errors_contact'] = [];
$errors = &$_SESSION['form_errors_contact'];

if (empty($nom_expediteur)) {
    $errors['nom'] = "Votre nom est requis.";
} elseif (strlen($nom_expediteur) > 150) {
    $errors['nom'] = "Votre nom ne doit pas dépasser 150 caractères.";
}

if (empty($email_expediteur)) {
    $errors['email'] = "Votre adresse e-mail est requise.";
} elseif (!filter_var($email_expediteur, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = "Format d'e-mail invalide.";
}

if (empty($sujet_contact)) {
    $errors['sujet'] = "Le sujet de votre message est requis.";
} elseif (strlen($sujet_contact) > 255) {
    $errors['sujet'] = "Le sujet ne doit pas dépasser 255 caractères.";
}

if (empty($message_contact)) {
    $errors['message'] = "Veuillez écrire le contenu de votre message.";
} elseif (strlen($message_contact) < 10) {
    $errors['message'] = "Votre message doit contenir au moins 10 caractères.";
} elseif (strlen($message_contact) > 5000) { // Limite pour le message
    $errors['message'] = "Votre message est trop long (maximum 5000 caractères).";
}

if (!empty($errors)) {
    $_SESSION['flash_message'] = "Votre message contient des erreurs. Veuillez vérifier les champs.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $form_origin_contact);
    exit;
}

// Enregistrement en base de données (table contact_messages)
try {
    $stmt_insert_contact = $pdo->prepare(
        "INSERT INTO contact_messages (nom_expediteur, email_expediteur, sujet, message) 
         VALUES (:nom, :email, :sujet, :message)"
    );
    $stmt_insert_contact->execute([
        ':nom' => $nom_expediteur,
        ':email' => $email_expediteur,
        ':sujet' => $sujet_contact,
        ':message' => $message_contact
    ]);
    $contact_message_id = $pdo->lastInsertId();

} catch (PDOException $e) {
    error_log("Erreur PDO enregistrement message contact: " . $e->getMessage());
    $_SESSION['flash_message'] = "Une erreur technique est survenue lors de la sauvegarde de votre message. Veuillez réessayer.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $form_origin_contact);
    exit;
}

// Envoi de l'email à l'administrateur
$admin_email_recipient = defined('CONTACT_FORM_RECIPIENT_EMAIL') ? CONTACT_FORM_RECIPIENT_EMAIL : 'admin@votresite.com'; // Fallback
$admin_name_recipient = defined('CONTACT_FORM_RECIPIENT_NAME') ? CONTACT_FORM_RECIPIENT_NAME : 'Administrateur SANTE TV';

$email_subject_to_admin = "Nouveau message de contact SANTE TV: " . $sujet_contact;
$email_body_to_admin = "<h1>Nouveau Message de Contact (ID: {$contact_message_id})</h1>
                        <p><strong>De :</strong> " . htmlspecialchars($nom_expediteur) . " (" . htmlspecialchars($email_expediteur) . ")</p>
                        <p><strong>Sujet :</strong> " . htmlspecialchars($sujet_contact) . "</p>
                        <hr>
                        <p><strong>Message :</strong></p>
                        <div style='padding:10px; border:1px solid #eee; background-color:#f9f9f9; white-space:pre-wrap;'>" . nl2br(htmlspecialchars($message_contact)) . "</div>
                        <hr>
                        <p><em>Message envoyé via le formulaire de contact du site SANTE TV.</em></p>";

if (function_exists('envoyer_email') && envoyer_email($admin_email_recipient, $admin_name_recipient, $email_subject_to_admin, $email_body_to_admin, '', $email_expediteur, $nom_expediteur)) {
    // Envoi d'un accusé de réception au visiteur (optionnel)
    $visitor_subject = "Votre message à SANTE TV a bien été reçu";
    $visitor_body = "<p>Bonjour " . htmlspecialchars($nom_expediteur) . ",</p>
                     <p>Nous avons bien reçu votre message concernant : \"" . htmlspecialchars($sujet_contact) . "\".</p>
                     <p>Notre équipe vous répondra dans les meilleurs délais.</p>
                     <p>Cordialement,<br>L'équipe SANTE TV</p>";
    envoyer_email($email_expediteur, $nom_expediteur, $visitor_subject, $visitor_body);

    $_SESSION['flash_message'] = "Merci pour votre message ! Nous vous répondrons dans les plus brefs délais.";
    $_SESSION['flash_type'] = "success";
    unset($_SESSION['form_data_contact']); 
} else {
    $_SESSION['flash_message'] = "Votre message a été enregistré, mais une erreur est survenue lors de l'envoi de la notification par e-mail. Nous traiterons votre demande manuellement.";
    $_SESSION['flash_type'] = "warning";
}

header("Location: " . $form_origin_contact);
exit;
?>